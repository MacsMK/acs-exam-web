<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Request;

final class GradebookCatalogContext
{
    private const RETURN_MARKER = 'from_gradebook';

    private const RETURN_PREFIX = 'gradebook_';

    private const RETURN_PAGE = 'gradebook_hub_page';

    /** @return array{search: string, status: string, subject: string} */
    public static function filters(Request $request, string $prefix = ''): array
    {
        $status = $request->query($prefix.'status');

        return [
            'search' => self::catalogText($request->query($prefix.'search')),
            'status' => in_array($status, ['active', 'closed'], true) ? $status : 'all',
            'subject' => self::catalogText($request->query($prefix.'subject')),
        ];
    }

    /** @param array{search: string, status: string, subject: string} $filters */
    public static function query(array $filters, int $page = 1): array
    {
        return array_filter([
            'search' => $filters['search'],
            'status' => $filters['status'] === 'all' ? '' : $filters['status'],
            'subject' => $filters['subject'],
            'page' => $page > 1 ? $page : '',
        ], static fn ($value): bool => $value !== '');
    }

    public static function returnQuery(array $query): array
    {
        return array_filter([
            self::RETURN_MARKER => 1,
            self::RETURN_PREFIX.'search' => $query['search'] ?? null,
            self::RETURN_PREFIX.'status' => $query['status'] ?? null,
            self::RETURN_PREFIX.'subject' => $query['subject'] ?? null,
            self::RETURN_PAGE => $query['page'] ?? null,
        ], static fn ($value): bool => $value !== null);
    }

    public static function fromReturn(Request $request): ?array
    {
        if (! in_array($request->query(self::RETURN_MARKER), ['1', 1], true)) {
            return null;
        }

        $page = filter_var($request->query(self::RETURN_PAGE), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return self::query(self::filters($request, self::RETURN_PREFIX), $page ?: 1);
    }

    public static function destinationQuery(Request $request): array
    {
        $query = self::fromReturn($request);

        return $query === null ? [] : self::returnQuery($query);
    }

    private static function catalogText(mixed $value): string
    {
        if (! is_string($value) || ! mb_check_encoding($value, 'UTF-8') || str_contains($value, "\0")) {
            return '';
        }

        return mb_substr(trim($value), 0, 120, 'UTF-8');
    }
}
