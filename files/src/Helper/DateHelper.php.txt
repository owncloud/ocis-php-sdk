<?php

namespace Owncloud\OcisPhpSdk\Helper;

use DateTime;
use Owncloud\OcisPhpSdk\Exception\DateException;

class DateHelper
{
    /**
     * @param string|DateTime $date
     * @return void
     * @throws DateException
     */
    public static function validateDeletionDate(string|DateTime $date): void
    {
        if ($date instanceof DateTime) {
            $dateString = $date->format('Y-m-d');
        } else {
            $dateString = $date;
        }
        $dt = DateTime::createFromFormat('Y-m-d', $dateString);
        if (!$dt || $dt->format('Y-m-d') !== $dateString) {
            throw new DateException("Invalid date format: '$dateString'. Expected format: YYYY-MM-DD.");
        }

        $now = new DateTime('today');
        $dt->setTime(0, 0, 0);

        // Validate date is in future
        if ($dt <= $now) {
            throw new DateException("Date '$dateString' is not in the future.");
        }
    }

    /**
     * @param string $relativeDate
     * @param DateTime $baseDate
     * @return string
     * @throws DateException
     */
    public static function getAbsoluteDateFromRelativeDate(string $relativeDate, DateTime $baseDate): string
    {
        if (!preg_match('/^(\d+)([dwmy])$/i', strtolower($relativeDate), $matches)) {
            throw new DateException("Invalid relative date format: '$relativeDate'.");
        }

        $num = (int)$matches[1];
        $unit = strtolower($matches[2]);

        if ($num <= 0) {
            throw new DateException("Relative date must be positive. '$relativeDate' is invalid (zero or negative).");
        }

        // Map unit to DateInterval modify string
        $modifyStr = match ($unit) {
            'd' => "+$num days",
            'w' => "+$num weeks",
            'm' => "+$num months",
            'y' => "+$num years",
            default => throw new \RuntimeException("Invalid unit after validation: '$unit'"),
        };
        $absoluteDate = (clone $baseDate)->modify($modifyStr);
        return $absoluteDate->format('Y-m-d');
    }
}
