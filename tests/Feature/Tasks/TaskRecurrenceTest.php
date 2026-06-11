<?php

namespace Tests\Feature\Tasks;

use App\Domains\Tasks\Enums\RecurrenceType;
use App\Domains\Tasks\Services\RecurrenceService;
use Carbon\Carbon;
use Tests\TestCase;

class TaskRecurrenceTest extends TestCase
{
    private RecurrenceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new RecurrenceService();
    }

    // ── Daily ─────────────────────────────────────────────────────────────────

    public function test_daily_adds_one_day_by_default(): void
    {
        $from = Carbon::parse('2026-06-11');
        $next = $this->service->calculateNextDate(RecurrenceType::Daily, [], $from);
        $this->assertEquals('2026-06-12', $next->toDateString());
    }

    public function test_daily_with_interval_adds_n_days(): void
    {
        $from = Carbon::parse('2026-06-11');
        $next = $this->service->calculateNextDate(RecurrenceType::Daily, ['interval' => 3], $from);
        $this->assertEquals('2026-06-14', $next->toDateString());
    }

    // ── Weekly ────────────────────────────────────────────────────────────────

    public function test_weekly_without_days_adds_one_week(): void
    {
        $from = Carbon::parse('2026-06-11'); // Thursday
        $next = $this->service->calculateNextDate(RecurrenceType::Weekly, [], $from);
        $this->assertEquals('2026-06-18', $next->toDateString());
    }

    public function test_weekly_with_interval_adds_n_weeks(): void
    {
        $from = Carbon::parse('2026-06-11');
        $next = $this->service->calculateNextDate(RecurrenceType::Weekly, ['interval' => 2], $from);
        $this->assertEquals('2026-06-25', $next->toDateString());
    }

    public function test_weekly_with_days_finds_next_matching_weekday(): void
    {
        $from = Carbon::parse('2026-06-11'); // Thursday (4)
        // Pattern: Mon (1) and Fri (5)
        $next = $this->service->calculateNextDate(RecurrenceType::Weekly, ['days_of_week' => [1, 5]], $from);
        $this->assertEquals('2026-06-12', $next->toDateString()); // Friday
    }

    // ── Monthly ───────────────────────────────────────────────────────────────

    public function test_monthly_adds_one_month_by_default(): void
    {
        $from = Carbon::parse('2026-06-11');
        $next = $this->service->calculateNextDate(RecurrenceType::Monthly, [], $from);
        $this->assertEquals('2026-07-11', $next->toDateString());
    }

    public function test_monthly_with_day_of_month_uses_fixed_day(): void
    {
        $from = Carbon::parse('2026-06-11');
        $next = $this->service->calculateNextDate(RecurrenceType::Monthly, ['day_of_month' => 20], $from);
        $this->assertEquals('2026-07-20', $next->toDateString());
    }

    public function test_monthly_clamps_to_last_day_of_short_month(): void
    {
        $from = Carbon::parse('2026-01-31');
        // day_of_month = 31 but February has only 28 days in 2026
        $next = $this->service->calculateNextDate(RecurrenceType::Monthly, ['day_of_month' => 31], $from);
        $this->assertEquals('2026-02-28', $next->toDateString());
    }

    // ── Yearly ────────────────────────────────────────────────────────────────

    public function test_yearly_adds_one_year_by_default(): void
    {
        $from = Carbon::parse('2026-06-11');
        $next = $this->service->calculateNextDate(RecurrenceType::Yearly, [], $from);
        $this->assertEquals('2027-06-11', $next->toDateString());
    }

    // ── Weekdays ──────────────────────────────────────────────────────────────

    public function test_weekdays_skips_saturday_to_monday(): void
    {
        $from = Carbon::parse('2026-06-12'); // Friday
        $next = $this->service->calculateNextDate(RecurrenceType::Weekdays, [], $from);
        $this->assertEquals('2026-06-15', $next->toDateString()); // Monday
    }

    public function test_weekdays_skips_sunday_to_monday(): void
    {
        $from = Carbon::parse('2026-06-13'); // Saturday (treated as from)
        $next = $this->service->calculateNextDate(RecurrenceType::Weekdays, [], $from);
        $this->assertFalse($next->isWeekend());
    }

    public function test_weekdays_from_thursday_goes_to_friday(): void
    {
        $from = Carbon::parse('2026-06-11'); // Thursday
        $next = $this->service->calculateNextDate(RecurrenceType::Weekdays, [], $from);
        $this->assertEquals('2026-06-12', $next->toDateString()); // Friday
    }

    // ── Custom ────────────────────────────────────────────────────────────────

    public function test_custom_days_interval(): void
    {
        $from = Carbon::parse('2026-06-11');
        $next = $this->service->calculateNextDate(RecurrenceType::Custom, ['interval' => 10, 'interval_unit' => 'days'], $from);
        $this->assertEquals('2026-06-21', $next->toDateString());
    }

    public function test_custom_weeks_interval(): void
    {
        $from = Carbon::parse('2026-06-11');
        $next = $this->service->calculateNextDate(RecurrenceType::Custom, ['interval' => 3, 'interval_unit' => 'weeks'], $from);
        $this->assertEquals('2026-07-02', $next->toDateString());
    }

    public function test_custom_months_interval(): void
    {
        $from = Carbon::parse('2026-06-11');
        $next = $this->service->calculateNextDate(RecurrenceType::Custom, ['interval' => 2, 'interval_unit' => 'months'], $from);
        $this->assertEquals('2026-08-11', $next->toDateString());
    }

    public function test_custom_defaults_to_days_for_invalid_unit(): void
    {
        $from = Carbon::parse('2026-06-11');
        $next = $this->service->calculateNextDate(RecurrenceType::Custom, ['interval' => 5, 'interval_unit' => 'invalid'], $from);
        $this->assertEquals('2026-06-16', $next->toDateString());
    }

    // ── Edge cases ────────────────────────────────────────────────────────────

    public function test_none_type_throws_exception(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service->calculateNextDate(RecurrenceType::None, [], Carbon::now());
    }

    public function test_interval_below_one_is_treated_as_one(): void
    {
        $from = Carbon::parse('2026-06-11');
        $next = $this->service->calculateNextDate(RecurrenceType::Daily, ['interval' => 0], $from);
        $this->assertEquals('2026-06-12', $next->toDateString());
    }
}
