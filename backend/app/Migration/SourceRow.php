<?php

namespace App\Migration;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

// Extends the MT-2.2 strict row contract for non-credential source metadata.
final class SourceRow
{
    public static function decode(string $source, string $table, array $row, string $timezone): array
    {
        if (! in_array($timezone, ['UTC', 'Asia/Karachi'], true) || ! isset($row['id']) || ! ctype_digit((string) $row['id']) || (int) $row['id'] < 1) {
            throw new InvalidArgumentException('Explicit source identity and timezone required.');
        }
        $manifest = json_decode(file_get_contents(base_path('../docs/schema/COLUMN_DESTINATIONS.json')), true, flags: JSON_THROW_ON_ERROR);
        $entry = collect($manifest['tables'])->first(fn ($entry) => $entry['source'] === $source && $entry['table'] === $table);
        if (! $entry) {
            throw new InvalidArgumentException('Unknown source table.');
        }
        $expected = array_column($entry['columns'], 'source_column');
        if (array_diff(array_keys($row), $expected) || array_diff($expected, array_keys($row))) {
            throw new InvalidArgumentException('Unknown or missing source columns.');
        }
        $values = [];
        foreach ($entry['columns'] as $column) {
            $name = $column['source_column'];
            if ($name === 'id') {
                continue;
            }
            $value = $row[$name];
            $type = $column['target_type'];
            if ($value === null && ! $type['nullable']) {
                throw new InvalidArgumentException('Required source field is null.');
            }
            if ($value !== null) {
                switch ($type['type']) {
                    case 'string': case 'char': case 'text': case 'longText':
                        if (! is_string($value) || mb_strlen($value) > ($type['length'] ?? 65535)) {
                            throw new InvalidArgumentException('Invalid source string.');
                        }
                        break;
                    case 'boolean':
                        if (! in_array($value, [true, false, 0, 1], true)) {
                            throw new InvalidArgumentException('Invalid source boolean.');
                        }
                        break;
                    case 'smallInteger': case 'integer': case 'bigInteger':
                        if (! is_int($value) || (($type['unsigned'] ?? false) && $value < 0)) {
                            throw new InvalidArgumentException('Invalid source integer.');
                        }
                        break;
                    case 'decimal':
                        $value = self::money($value);
                        break;
                    case 'dateTime':
                        $date = is_string($value) ? DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value, new DateTimeZone($timezone)) : false;
                        if (! $date || $date->format('Y-m-d H:i:s') !== $value) {
                            throw new InvalidArgumentException('Invalid source timestamp.');
                        }
                        $value = $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');
                        break;
                    case 'json':
                        $value = json_encode(is_string($value) ? json_decode($value, true, flags: JSON_THROW_ON_ERROR) : $value, JSON_THROW_ON_ERROR);
                        break;
                    default:
                        throw new InvalidArgumentException('Unsupported source type.');
                }
            }
            [, $destination] = explode('.', $column['destination']);
            $values[$destination] = $value;
        }

        return [$entry['target_table'], $values];
    }

    public static function money(mixed $value): string
    {
        if ((! is_string($value) && ! is_int($value)) || ! preg_match('/\A\d{1,17}(?:\.\d{1,2})?\z/', (string) $value)) {
            throw new InvalidArgumentException('Money requires an exact non-negative decimal string.');
        }

        return bcadd((string) $value, '0', 2);
    }
}
