<?php

class Platron_Form_Builder {
	public static $field_size = 50;
	public static $max_size = 150;

	public static function text($id, $field, $data) {
		$value = self::get($id, $field, $data);
		$output = "<input type='text' id='{$id}' name='{$id}' size='" . self::$field_size . "' maxlength='" . self::$max_size . "' value='{$value}' />";
		return self::output($output, $field);
	}

	public static function select($id, $field, $data) {
		$value = self::get($id, $field, $data);
		$options = '<option>--- SELECT ---</option>';
		foreach($field['options'] as $key => $label) {
			$selected = ($key === $value) ? 'selected' : '';
			$options .= "<option value='{$key}' {$selected}>{$label}</option>";
		}
		$output = "<select name='{$id}'>{$options}</selected";
		return self::output($output, $field);
	}

	public static function checkbox($id, $field, $data) {
		$value = self::get($id, $field, $data);
		$checked = ($value === 'yes') ? 'checked' : ''; 
		$output = "<input type='checkbox' name='{$id}' value='yes' {$checked} />";
		return self::output($output, $field);
	}

	public static function get($id, $field, $data) {
		if( ! isset($data[$id])) {
			if(isset($field['default'])) {
				return $field['default'];
			}
			return '';
		} 
		return $data[$id];
	}

	public static function output($output, $field) {
		$html = "<tr valign='top'>
					<th scope='row'><label for='{$id}'>{$field['title']}:</label></th>
					<td>{$output}</td>
				 </tr>";
		echo $html;
	}
}
?>