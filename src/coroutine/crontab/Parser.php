<?php
// 代码来自hyperf
namespace think\swow\coroutine\crontab;

use InvalidArgumentException;

class Parser
{
    /**
     *  解析crontab的定时格式，linux只支持到分钟/，这个类支持到秒.
     *
     * @param string $crontabString :
     *                              0    1    2    3    4    5
     *                              *    *    *    *    *    *
     *                              -    -    -    -    -    -
     *                              |    |    |    |    |    |
     *                              |    |    |    |    |    +----- day of week (0 - 6) (Sunday=0)
     *                              |    |    |    |    +----- month (1 - 12)
     *                              |    |    |    +------- day of month (1 - 31)
     *                              |    |    +--------- hour (0 - 23)
     *                              |    +----------- min (0 - 59)
     *                              +------------- sec (0-59)
     * @return array
     * @throws InvalidArgumentException
     */
    public function parse(string $crontabString)
    {
        if (! $this->isValid($crontabString)) {
            throw new InvalidArgumentException('Invalid cron string: ' . $crontabString);
        }
        $startTime = time();
        $date = $this->parseDate($crontabString);
        if (in_array((int) date('i', $startTime), $date['minutes'])
            && in_array((int) date('G', $startTime), $date['hours'])
            && in_array((int) date('j', $startTime), $date['day'])
            && in_array((int) date('w', $startTime), $date['week'])
            && in_array((int) date('n', $startTime), $date['month'])
        ) {
            $result = [];
            foreach ($date['second'] as $second) {
                $result[] = $startTime + $second;
            }
            return $result;
        }
        return [];
    }

    public function isValid(string $crontabString): bool
    {
        if (! preg_match('/^((\*(\/[0-9]+)?)|[0-9\-\,\/]+)\s+((\*(\/[0-9]+)?)|[0-9\-\,\/]+)\s+((\*(\/[0-9]+)?)|[0-9\-\,\/]+)\s+((\*(\/[0-9]+)?)|[0-9\-\,\/]+)\s+((\*(\/[0-9]+)?)|[0-9\-\,\/]+)\s+((\*(\/[0-9]+)?)|[0-9\-\,\/]+)$/i', trim($crontabString))) {
            if (! preg_match('/^((\*(\/[0-9]+)?)|[0-9\-\,\/]+)\s+((\*(\/[0-9]+)?)|[0-9\-\,\/]+)\s+((\*(\/[0-9]+)?)|[0-9\-\,\/]+)\s+((\*(\/[0-9]+)?)|[0-9\-\,\/]+)\s+((\*(\/[0-9]+)?)|[0-9\-\,\/]+)$/i', trim($crontabString))) {
                return false;
            }
        }
        return true;
    }

    /**
     * Parse each segment of crontab string.
     */
    protected function parseSegment(string $string, int $min, int $max, int $start = null)
    {
        if ($start === null || $start < $min) {
            $start = $min;
        }
        $result = [];
        if ($string === '*') {
            for ($i = $start; $i <= $max; ++$i) {
                $result[] = $i;
            }
        } elseif (str_contains($string, ',')) {
            $exploded = explode(',', $string);
            foreach ($exploded as $value) {
                if (str_contains($value, '/') || str_contains($string, '-')) {
                    $result = array_merge($result, $this->parseSegment($value, $min, $max, $start));
                    continue;
                }

                if (trim($value) === '' || ! $this->between((int) $value, $min > $start ? $min : $start, $max)) {
                    continue;
                }
                $result[] = (int) $value;
            }
        } elseif (str_contains($string, '/')) {
            $exploded = explode('/', $string);
            if (str_contains($exploded[0], '-')) {
                [$nMin, $nMax] = explode('-', $exploded[0]);
                $nMin > $min && $min = (int) $nMin;
                $nMax < $max && $max = (int) $nMax;
            }
            // If the value of start is larger than the value of min, the value of start should equal with the value of min.
            $start < $min && $start = $min;
            for ($i = $start; $i <= $max;) {
                $result[] = $i;
                $i += $exploded[1];
            }
        } elseif (str_contains($string, '-')) {
            $result = array_merge($result, $this->parseSegment($string . '/1', $min, $max, $start));
        } elseif ($this->between((int) $string, $min > $start ? $min : $start, $max)) {
            $result[] = (int) $string;
        }
        return $result;
    }

    /**
     * Determine if the $value is between in $min and $max ?
     */
    private function between(int $value, int $min, int $max): bool
    {
        return $value >= $min && $value <= $max;
    }

    private function parseDate(string $crontabString): array
    {
        $cron = preg_split('/[\\s]+/i', trim($crontabString));
        if (count($cron) == 6) {
            $date = [
                'second' => $this->parseSegment($cron[0], 0, 59),
                'minutes' => $this->parseSegment($cron[1], 0, 59),
                'hours' => $this->parseSegment($cron[2], 0, 23),
                'day' => $this->parseSegment($cron[3], 1, 31),
                'month' => $this->parseSegment($cron[4], 1, 12),
                'week' => $this->parseSegment($cron[5], 0, 6),
            ];
        } else {
            $date = [
                'second' => [1 => 0],
                'minutes' => $this->parseSegment($cron[0], 0, 59),
                'hours' => $this->parseSegment($cron[1], 0, 23),
                'day' => $this->parseSegment($cron[2], 1, 31),
                'month' => $this->parseSegment($cron[3], 1, 12),
                'week' => $this->parseSegment($cron[4], 0, 6),
            ];
        }
        return $date;
    }
}