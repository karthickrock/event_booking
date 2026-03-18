<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;

trait CommonQueryScopes
{
    /**
     * Filter by date range
     * 
     * @param Builder $query
     * @param string $startDate
     * @param string $endDate
     * @return Builder
     */
    public function scopeFilterByDate(Builder $query, string $startDate, string $endDate): Builder
    {
        return $query->whereBetween('date', [$startDate, $endDate]);
    }

    /**
     * Search by title or description
     * 
     * @param Builder $query
     * @param string $searchTerm
     * @return Builder
     */
    public function scopeSearchByTitle(Builder $query, string $searchTerm): Builder
    {
        return $query->where('title', 'LIKE', "%{$searchTerm}%")
            ->orWhere('description', 'LIKE', "%{$searchTerm}%");
    }

    /**
     * Filter by location
     * 
     * @param Builder $query
     * @param string $location
     * @return Builder
     */
    public function scopeFilterByLocation(Builder $query, string $location): Builder
    {
        return $query->where('location', 'LIKE', "%{$location}%");
    }
}
