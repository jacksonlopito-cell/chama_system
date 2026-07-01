<?php
/**
 * Input Validation Functions
 */

/**
 * Validate email
 */
function validateEmail($email): bool {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate phone number (Kenyan format)
 */
function validatePhone($phone): bool {
    return preg_match('/^\+?254\d{9}$/', $phone) || preg_match('/^0\d{9}$/', $phone);
}

/**
 * Validate required field
 */
function validateRequired($value): bool {
    return isset($value) && trim((string)$value) !== '';
}

/**
 * Validate minimum length
 */
function validateMinLength($value, $min): bool {
    return strlen(trim((string)$value)) >= $min;
}

/**
 * Validate maximum length
 */
function validateMaxLength($value, $max): bool {
    return strlen(trim((string)$value)) <= $max;
}

/**
 * Validate numeric
 */
function validateNumeric($value): bool {
    return is_numeric($value);
}

/**
 * Validate integer
 */
function validateInteger($value): bool {
    return filter_var($value, FILTER_VALIDATE_INT) !== false;
}

/**
 * Validate positive number
 */
function validatePositive($value): bool {
    return is_numeric($value) && (float)$value > 0;
}

/**
 * Validate date
 */
function validateDate($date, $format = 'Y-m-d'): bool {
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}

/**
 * Validate URL
 */
function validateUrl($url): bool {
    return filter_var($url, FILTER_VALIDATE_URL) !== false;
}

/**
 * Validate national ID
 */
function validateNationalId($id): bool {
    return preg_match('/^\d{6,8}$/', $id);
}

/**
 * Validate against XSS
 */
function sanitizeInput($value): string {
    return htmlspecialchars(trim((string)$value), ENT_QUOTES, 'UTF-8');
}

/**
 * Sanitize array input
 */
function sanitizeArray($data): array {
    $clean = [];
    foreach ($data as $key => $value) {
        $clean[$key] = is_array($value)
            ? sanitizeArray($value)
            : sanitizeInput($value);
    }
    return $clean;
}

/**
 * Validate and sanitize form data
 */
function validateForm($rules, $data): array {
    $errors = [];
    $validated = [];

    foreach ($rules as $field => $ruleSet) {
        $value = $data[$field] ?? '';
        $label = $ruleSet['label'] ?? ucfirst(str_replace('_', ' ', $field));
        $validated[$field] = trim((string)$value);

        foreach ($ruleSet['rules'] as $rule => $param) {
            switch ($rule) {
                case 'required':
                    if (!validateRequired($value)) {
                        $errors[$field] = "$label is required";
                    }
                    break;

                case 'email':
                    if (!empty($value) && !validateEmail($value)) {
                        $errors[$field] = "Invalid email format";
                    }
                    break;

                case 'phone':
                    if (!empty($value) && !validatePhone($value)) {
                        $errors[$field] = "Invalid phone number format";
                    }
                    break;

                case 'min':
                    if (!validateMinLength($value, $param)) {
                        $errors[$field] = "$label must be at least $param characters";
                    }
                    break;

                case 'max':
                    if (!validateMaxLength($value, $param)) {
                        $errors[$field] = "$label must not exceed $param characters";
                    }
                    break;

                case 'numeric':
                    if (!empty($value) && !validateNumeric($value)) {
                        $errors[$field] = "$label must be a number";
                    }
                    break;

                case 'positive':
                    if (!empty($value) && !validatePositive($value)) {
                        $errors[$field] = "$label must be a positive number";
                    }
                    break;

                case 'date':
                    if (!empty($value) && !validateDate($value)) {
                        $errors[$field] = "Invalid date format for $label";
                    }
                    break;

                case 'national_id':
                    if (!empty($value) && !validateNationalId($value)) {
                        $errors[$field] = "Invalid National ID format";
                    }
                    break;

                case 'matches':
                    if ($value !== ($data[$param] ?? '')) {
                        $errors[$field] = "$label does not match";
                    }
                    break;

                case 'unique':
                    list($table, $column, $excludeId) = array_pad((array)$param, 3, null);
                    $db = getConnection();
                    $sql = "SELECT COUNT(*) FROM $table WHERE $column = ?";
                    $params = [$value];
                    if ($excludeId) {
                        $sql .= " AND id != ?";
                        $params[] = $excludeId;
                    }
                    $stmt = $db->prepare($sql);
                    $stmt->execute($params);
                    if ($stmt->fetchColumn() > 0) {
                        $errors[$field] = "$label already exists";
                    }
                    break;
            }
        }
    }

    return [
        'valid'     => empty($errors),
        'errors'    => $errors,
        'validated' => $validated
    ];
}
