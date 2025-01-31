<?php

declare(strict_types=1);

/*
 * This file is part of the Extension "sf_event_mgt" for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

namespace DERHANSEN\SfEventMgt\Service;

use DateTime;
use DERHANSEN\SfEventMgt\Domain\Model\Event;
use TYPO3\CMS\Extbase\Persistence\QueryResultInterface;

/**
 * CalendarService
 */
class CalendarService
{
    /**
     * Returns an array with weeks/days for the calendar view
     *
     * @param int $month
     * @param int $year
     * @param int $today
     * @param int $firstDayOfWeek
     * @param array|QueryResultInterface $events
     * @return array
     */
    public function getCalendarArray(int $month, int $year, int $today, int $firstDayOfWeek = 0, $events = null): array
    {
        $weeks = [];
        $dateRange = $this->getCalendarDateRange($month, $year, $firstDayOfWeek);
        $currentDay = $dateRange['firstDayOfCalendar'];
        while ($currentDay <= $dateRange['lastDayOfCalendar']) {
            $week = [];
            for ($d = 0; $d < 7; $d++) {
                $day = [];
                $day['timestamp'] = $currentDay;
                $day['day'] = (int)date('j', $currentDay);
                $day['month'] = (int)date('n', $currentDay);
                $day['weekNumber'] = (int)date('W', $currentDay);
                $day['isCurrentMonth'] = $day['month'] === $month;
                $day['isCurrentDay'] = date('Ymd', $today) === date('Ymd', $day['timestamp']);
                if ($events) {
                    $searchDay = new \DateTime();
                    $searchDay->setTimestamp($currentDay);
                    $day['events'] = $this->getEventsForDay($events, $searchDay);
                }
                $currentDay = strtotime('+1 day', $currentDay);
                $week[] = $day;
            }
            $weeks[] = $week;
        }

        return $weeks;
    }

    /**
     * Returns an array with meta information about the calendar date range for the month of the given year
     * respecting the firstDayOfWeek setting
     *
     * @param int $month
     * @param int $year
     * @param int $firstDayOfWeek
     * @return array
     */
    public function getCalendarDateRange(int $month, int $year, int $firstDayOfWeek = 0): array
    {
        $firstDayOfMonth = mktime(0, 0, 0, $month, 1, $year);
        $dayOfWeekOfFirstDay = (int)date('w', $firstDayOfMonth);
        $firstDayOfCalendarOffset = 1 - $dayOfWeekOfFirstDay + $firstDayOfWeek;
        if ($firstDayOfCalendarOffset > 1) {
            $firstDayOfCalendarOffset -= 7;
        }
        $firstDayOfCalendar = mktime(0, 0, 0, $month, 0 + $firstDayOfCalendarOffset, $year);

        $lastDayOfMonth = mktime(0, 0, 0, $month + 1, 0, $year);
        $dayOfWeekOfLastDay = (int)date('w', $lastDayOfMonth);
        $lastDayOfCalendarOffset = 6 - $dayOfWeekOfLastDay + $firstDayOfWeek;
        if ($dayOfWeekOfLastDay === 0 && $firstDayOfWeek === 1) {
            $lastDayOfCalendarOffset = 0;
        }
        $lastDayOfCalendar = strtotime('+' . $lastDayOfCalendarOffset . ' days', $lastDayOfMonth);

        return [
            'firstDayOfMonth' => $firstDayOfMonth,
            'lastDayOfMonth' => $lastDayOfMonth,
            'firstDayOfCalendar' => $firstDayOfCalendar,
            'lastDayOfCalendar' => $lastDayOfCalendar,
        ];
    }

    /**
     * Returns an array of events for the given day
     *
     * @param array|QueryResultInterface $events
     * @param DateTime $currentDay
     * @return array
     */
    protected function getEventsForDay($events, DateTime $currentDay): array
    {
        $foundEvents = [];
        /** @var Event $event */
        foreach ($events as $event) {
            $eventBeginDate = $event->getStartdate()->format('Y-m-d');
            $day = date('Y-m-d', $currentDay->getTimestamp());
            if (!is_a($event->getEnddate(), DateTime::class)) {
                if ($eventBeginDate === $day) {
                    $foundEvents[] = $event;
                }
            } else {
                $eventEndDate = clone $event->getEnddate();
                $eventEndDate->setTime(23, 59, 59);
                $eventBeginDate = clone $event->getStartdate();
                $eventBeginDate->setTime(0, 0);
                $currentDay->setTime(0, 0);
                if ($eventBeginDate <= $currentDay && $eventEndDate >= $currentDay) {
                    $foundEvents[] = $event;
                }
            }
        }

        return $foundEvents;
    }

    /**
     * Returns a date configuration for the given modifier
     *
     * @param int $month
     * @param int $year
     * @param string $modifier
     * @return array
     */
    public function getDateConfig(int $month, int $year, string $modifier = ''): array
    {
        $date = \DateTime::createFromFormat('d.m.Y', sprintf('1.%s.%s', $month, $year));
        $date->setTime(0, 0, 0);
        if (!empty($modifier)) {
            $date->modify($modifier);
        }

        return [
            'date' => $date,
            'month' => (int)$date->format('n'),
            'year' => (int)$date->format('Y'),
        ];
    }
}
