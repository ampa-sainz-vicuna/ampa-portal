<?php

declare(strict_types=1);

namespace App\Portal\Domain\Calendar;

interface SchoolYearRepository
{
    public function find(string $code): ?SchoolYear;

    /** @return list<SchoolYear> del más reciente al más viejo */
    public function findAll(): array;

    public function save(SchoolYear $schoolYear): void;
}
