<?php

/**
 * Private, bounded Abilities API registration and exact JSON validation.
 *
 * @package PublishPress\Checklists
 * @author PublishPress
 * @copyright Copyright (c) 2026, PublishPress
 * @license GPLv2 or later
 */

declare(strict_types=1);

namespace PublishPress\Checklists\Core\Mcp;

\defined('ABSPATH') || exit;

class Registry
{
    /** @since 2.31.0 */
    public static function object(array $properties = [], array $required = []): array
    {
        $schema = ['type' => 'object', 'additionalProperties' => false];
        if ($properties) {
            $schema['properties'] = $properties;
        }
        if ($required) {
            $schema['required'] = $required;
        }
        return $schema;
    }

    /** @since 2.31.0 */
    public static function id(): array
    {
        return ['type' => 'integer', 'minimum' => 1];
    }

    /** @since 2.31.0 */
    public static function page(): array
    {
        return ['page' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 10000, 'default' => 1],
            'per_page' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 20]];
    }

    /** @since 2.31.0 */
    public static function preview(): array
    {
        return ['type' => 'boolean', 'default' => false];
    }

    /** @since 2.31.0 */
    public static function revision(): array
    {
        return ['type' => 'string', 'pattern' => '^[a-f0-9]{64}$'];
    }

    /** @since 2.31.0 */
    public static function error(string $message, int $status = 400): \WP_Error
    {
        return new \WP_Error('publishpress_checklists_invalid_operation', $message, ['status' => $status]);
    }

    /** @since 2.31.0 */
    public static function validate($value, array $schema)
    {
        if (!self::strict($value, $schema)) {
            return self::error(__('Use the exact field types returned by discovery.', 'publishpress-checklists'));
        }
        return rest_validate_value_from_schema($value, $schema, 'input');
    }

    /** @since 2.31.0 */
    public static function strict($value, array $schema, int $depth = 0): bool
    {
        if ($depth > 40) {
            return false;
        }
        foreach (['oneOf', 'anyOf'] as $keyword) {
            if (isset($schema[$keyword])) {
                foreach ($schema[$keyword] as $variant) {
                    if (self::strict($value, $variant, $depth + 1) && !is_wp_error(rest_validate_value_from_schema($value, $variant))) {
                        return true;
                    }
                }
                return false;
            }
        }
        $types = (array) ($schema['type'] ?? []);
        $actual = is_bool($value) ? 'boolean' : (is_int($value) ? 'integer' : (is_float($value) ? 'number'
            : (is_string($value) ? 'string' : ($value === null ? 'null' : (is_array($value) || is_object($value) ? 'object' : 'invalid')))));
        if (is_array($value) && in_array('array', $types, true) && array_is_list($value)) {
            $actual = 'array';
        }
        if ($types && !in_array($actual, $types, true) && !($actual === 'integer' && in_array('number', $types, true))) {
            return false;
        }
        if (is_array($value) || is_object($value)) {
            foreach ((array) $value as $key => $child) {
                $next = $schema['properties'][$key] ?? ($schema['items'] ?? ($schema['additionalProperties'] ?? []));
                if ($next === false || !self::strict($child, is_array($next) ? $next : [], $depth + 1)) {
                    return false;
                }
            }
        }
        return true;
    }

    /** @since 2.31.0 */
    public static function fingerprint($value): string
    {
        $canonical = static function ($value) use (&$canonical) {
            if (is_object($value)) {
                $value = (array) $value;
            }
            if (!is_array($value)) {
                return $value;
            }
            if (!array_is_list($value)) {
                ksort($value);
            }
            return array_map($canonical, $value);
        };
        $encoded = wp_json_encode($canonical($value));
        if ($encoded === false) {
            throw new \InvalidArgumentException('Data could not be encoded.');
        }
        return hash('sha256', $encoded);
    }

    /** @since 2.31.0 */
    public static function add(string $name, string $label, string $description, array $input, array $output, callable $execute, callable $permission, bool $write = false, bool $idempotent = true): void
    {
        wp_register_ability('publishpress-checklists/' . $name, [
            'label' => $label, 'description' => $description, 'category' => 'publishpress',
            'input_schema' => $input, 'output_schema' => self::object($output, array_keys($output)),
            'permission_callback' => static function ($values = []) use ($input, $permission) {
                if (!is_array($values) || is_wp_error(self::validate($values, $input))) {
                    return false;
                }
                try {
                    return (bool) $permission($values);
                } catch (\Throwable $error) {
                    return false;
                }
            },
            'execute_callback' => static function (array $values = []) use ($input, $execute, $permission, $write) {
                foreach ($input['properties'] ?? [] as $key => $field) {
                    if (!array_key_exists($key, $values) && array_key_exists('default', $field)) {
                        $values[$key] = $field['default'];
                    }
                }
                $valid = self::validate($values, $input);
                if (is_wp_error($valid) || strlen((string) wp_json_encode($values)) > 1048576) {
                    return is_wp_error($valid) ? $valid : self::error(__('The request is too large.', 'publishpress-checklists'));
                }
                try {
                    if (!$permission($values)) {
                        return self::error(__('You do not have permission for this action.', 'publishpress-checklists'), 403);
                    }
                    $result = $execute($values);
                    if (!is_wp_error($result) && strlen((string) wp_json_encode($result)) > 2097152) {
                        return self::error(__('The result is too large. Use narrower discovery or a smaller page.', 'publishpress-checklists'));
                    }
                    return $result;
                } catch (\Throwable $error) {
                    return self::error($write
                        ? __('The operation did not complete normally. Read the affected data before retrying; some changes may have been applied.', 'publishpress-checklists')
                        : __('The data could not be read. Check the active integrations before retrying.', 'publishpress-checklists'), 500);
                }
            },
            'meta' => ['public' => false, 'show_in_rest' => false, 'mcp' => ['public' => false],
                'annotations' => ['readonly' => !$write, 'destructive' => $write, 'idempotent' => $idempotent]],
        ]);
    }
}
