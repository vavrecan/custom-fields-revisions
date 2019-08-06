<?php
/**
 * Plugin Name: Custom Fields Revisions
 * Plugin URI: http://github.com/vavrecan
 * Description: Show custom fields on the revision page
 * Version: 1.3
 * Author: Marek Vavrecan (vavrecan@gmail.com)
 **/

/**
 * Filter out all private custom fields that starts with underscore character
 * @param $meta
 * @return array
 */
function cfr_filter_meta($meta) {
    $meta_filtered = [];
    foreach ($meta as $key => $value) {
        if ($key{0} != "_")
            $meta_filtered[$key] = $value;
    }
    return $meta_filtered;
}

/**
 * Get filtered custom fields from metadata
 * @param $post_id
 * @return array
 */
function cfr_get_meta($post_id) {
    $meta = get_metadata("post", $post_id);
    $meta = cfr_filter_meta($meta);
    return $meta;
}

/**
 * Save custom fields with post / revision post
 * @param $post_id
 * @param $meta
 */
function cfr_insert_meta($post_id, $meta) {
    foreach ($meta as $meta_key => $meta_value) {
        if (is_array($meta_value))
            foreach ($meta_value as $single_meta_value)
                add_metadata('post', $post_id, $meta_key, maybe_unserialize($single_meta_value));
        else
            add_metadata('post', $post_id, $meta_key, $meta_value);
    }
}

function cfr_delete_meta($post_id) {
    $meta = cfr_get_meta($post_id);

    foreach ($meta as $meta_key => $meta_value) {
        delete_metadata('post', $post_id, $meta_key);
    }
}

/**
 * Creates text format from custom fields
 * @param $value
 * @param $field
 * @param $revision
 * @return string
 */
function cfr_field($value, $field, $revision)
{
    $revision_id = $revision->ID;
    $meta = cfr_get_meta($revision_id);

    // format response as single string with all custom fields / metadata
    $return = "";
    foreach ($meta as $meta_key => $meta_value) {
        $return .= $meta_key . ": " . join(", ", $meta_value) . "\n";
    }

    return $return;
}

/**
 * Create new field in revision view with title Custom Fields
 * @param $fields
 * @return mixed
 */
function cfr_fields($fields)
{
    $fields["custom_fields"] = "Custom Fields";
    return $fields;
}

function cfr_restore_revision($post_id, $revision_id)
{
    $meta = cfr_get_meta($revision_id);
    cfr_delete_meta($post_id);
    cfr_insert_meta($post_id, $meta);

    // also update last revision custom fields
    $revisions = wp_get_post_revisions($post_id);
    if (count($revisions) > 0) {
        $last_revision = current($revisions);
        cfr_delete_meta($last_revision->ID);
        cfr_insert_meta($last_revision->ID, $meta);
    }
}

/**
 * Wordpress hook callback to save metadata to revision post
 * @param $post_id
 * @param $post
 */
function cfr_save_post($post_id, $post)
{
    if ($parent_id = wp_is_post_revision($post_id)) {
        $meta = cfr_get_meta(get_post($parent_id)->ID);
        if ($meta === false)
            return;

        cfr_insert_meta($post_id, $meta);
    }
}

/**
 * Check if custom fields were changed to make sure new revision will be created
 * even when user did not modified title or content of the post
 *
 * @param $post_has_changed
 * @param $last_revision
 * @param $post
 * @return bool
 */
function cfr_post_has_changed($post_has_changed, $last_revision, $post) {
    if (!$post_has_changed)
    {
        $meta = cfr_get_meta(get_post($last_revision)->ID);
        $meta_new = cfr_get_meta($post->ID);

        if ($meta === $meta_new)
            return $post_has_changed;

        // post changed
        return true;
    }
    return $post_has_changed;
}

add_action('save_post', 'cfr_save_post', 10, 2);
add_action('wp_restore_post_revision', 'cfr_restore_revision', 10, 2);
add_filter('wp_save_post_revision_post_has_changed', 'cfr_post_has_changed', 10, 3);

add_filter('_wp_post_revision_fields', 'cfr_fields', 10, 1);
add_filter("_wp_post_revision_field_custom_fields", "cfr_field", 10, 3);

