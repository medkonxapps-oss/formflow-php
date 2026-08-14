<?php

declare(strict_types=1);

namespace FormFlow;

/**
 * Server-side field validation mirroring form definitions (PRD §6.5 Layer 4/5).
 */
class FormValidator
{
  /**
   * @param list<array<string, mixed>> $fields
   * @param array<string, mixed> $payload
   * @return array<string, string> field_id => error message
   */
  public static function validate(array $fields, array $payload): array
  {
    $errors = [];

    foreach ($fields as $field) {
      if (!is_array($field)) {
        continue;
      }

      $type = (string) ($field['type'] ?? '');
      if (in_array($type, ['heading', 'paragraph'], true)) {
        continue;
      }

      $id = (string) ($field['id'] ?? '');
      if ($id === '') {
        continue;
      }

      $value = $payload[$id] ?? null;
      $required = !empty($field['required']);
      $validation = is_array($field['validation'] ?? null) ? $field['validation'] : [];
      $customError = trim((string) ($validation['error_message'] ?? ''));

      if ($type === 'file') {
        $file = $_FILES[$id] ?? null;
        $errorCode = is_array($file) ? (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) : UPLOAD_ERR_NO_FILE;
        $hasFile = $errorCode !== UPLOAD_ERR_NO_FILE;

        if ($required && !$hasFile) {
          $errors[$id] = $customError !== '' ? $customError : 'This field is required.';
        } elseif ($hasFile && $errorCode !== UPLOAD_ERR_OK) {
          $errors[$id] = $customError !== '' ? $customError : 'Upload failed.';
        }
        continue;
      }

      if ($type === 'checkbox') {
        if ($required && (!is_array($value) || $value === [])) {
          $errors[$id] = $customError !== '' ? $customError : 'Please select at least one option.';
        }
        if (is_array($value) && $value !== []) {
          self::validateOptions($field, $value, $id, $errors, $customError);
        }
        continue;
      }

      if ($type === 'single-checkbox') {
        if ($required && empty($value)) {
          $errors[$id] = $customError !== '' ? $customError : 'This field is required.';
        }
        continue;
      }

      $strVal = is_array($value) ? implode(',', $value) : trim((string) ($value ?? ''));

      if ($required && $strVal === '') {
        $errors[$id] = $customError !== '' ? $customError : 'This field is required.';
        continue;
      }

      if ($strVal === '') {
        continue;
      }

      if (in_array($type, ['select', 'radio'], true)) {
        self::validateOptions($field, [$strVal], $id, $errors, $customError);
      }

      $minLen = (int) ($validation['min_length'] ?? 0);
      $maxLen = (int) ($validation['max_length'] ?? 0);

      if ($minLen > 0 && mb_strlen($strVal) < $minLen) {
        $errors[$id] = $customError !== '' ? $customError : "Must be at least {$minLen} characters.";
      }

      if ($maxLen > 0 && mb_strlen($strVal) > $maxLen) {
        $errors[$id] = $customError !== '' ? $customError : "Must be no more than {$maxLen} characters.";
      }

      $regex = trim((string) ($validation['regex'] ?? ''));
      if ($regex !== '' && !str_contains($regex, '/') && @preg_match('/' . $regex . '/u', $strVal) !== 1) {
        $errors[$id] = $customError !== '' ? $customError : 'Invalid format.';
      }

      if ($type === 'email' && !filter_var($strVal, FILTER_VALIDATE_EMAIL)) {
        $errors[$id] = $customError !== '' ? $customError : 'Please enter a valid email address.';
      }

      if ($type === 'url' && !filter_var($strVal, FILTER_VALIDATE_URL)) {
        $errors[$id] = $customError !== '' ? $customError : 'Please enter a valid URL.';
      }

      if ($type === 'number' && !is_numeric($strVal)) {
        $errors[$id] = $customError !== '' ? $customError : 'Please enter a valid number.';
      }
    }

    return $errors;
  }

  /**
   * @param array<string, mixed> $field
   * @param list<string> $values
   * @param array<string, string> $errors
   */
  private static function validateOptions(array $field, array $values, string $id, array &$errors, string $customError): void
  {
    $options = is_array($field['options'] ?? null) ? $field['options'] : [];
    $allowed = [];
    foreach ($options as $opt) {
      if (is_array($opt)) {
        $allowed[] = (string) ($opt['value'] ?? '');
      }
    }

    foreach ($values as $val) {
      if (!in_array((string) $val, $allowed, true)) {
        $errors[$id] = $customError !== '' ? $customError : 'Invalid selection.';
        break;
      }
    }
  }
}
