<?php
/**
 * EOS To-Dos Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class EOS_Todos {
    /**
     * Create a new to-do item
     */
    public static function create_todo($data) {
        global $wpdb;
        $table = EOS_Database::get_table_name('todos');

        $todo = array(
            'title'       => '',
            'description' => '',
            'assignee'    => '',
            'due_date'    => '',
            'status'      => 'pending',
            'meeting_id'  => 0,
            'created_by'  => get_current_user_id(),
        );

        $todo = wp_parse_args($data, $todo);
        $todo['title']    = sanitize_text_field($todo['title']);
        $todo['description'] = wp_kses_post($todo['description']);
        $todo['assignee'] = sanitize_text_field($todo['assignee']);
        $todo['due_date'] = $todo['due_date'] ? sanitize_text_field($todo['due_date']) : null;
        $todo['meeting_id'] = intval($todo['meeting_id']);
        $todo['status']   = in_array($todo['status'], array('pending','completed','overdue')) ? $todo['status'] : 'pending';

        $result = $wpdb->insert($table, $todo);
        return $result !== false;
    }

    /**
     * Fetch a to-do item
     */
    public static function get_todo($id) {
        global $wpdb;
        $table = EOS_Database::get_table_name('todos');
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id), ARRAY_A);
    }

    /**
     * Update a to-do item
     */
    public static function update_todo($id, $data) {
        global $wpdb;
        $table = EOS_Database::get_table_name('todos');

        $allowed = array('title','description','assignee','due_date','status');
        $update = array();
        foreach ($allowed as $field) {
            if (isset($data[$field])) {
                switch ($field) {
                    case 'description':
                        $update[$field] = wp_kses_post($data[$field]);
                        break;
                    case 'due_date':
                        $update[$field] = sanitize_text_field($data[$field]);
                        break;
                    default:
                        $update[$field] = sanitize_text_field($data[$field]);
                }
            }
        }

        if (empty($update)) {
            return false;
        }

        $result = $wpdb->update($table, $update, array('id' => intval($id)));
        return $result !== false;
    }

    /**
     * Delete a to-do item
     */
    public static function delete_todo($id) {
        global $wpdb;
        $table = EOS_Database::get_table_name('todos');
        $result = $wpdb->delete($table, array('id' => intval($id)));
        return $result !== false;
    }
}
