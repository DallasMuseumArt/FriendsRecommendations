<?php namespace DMA\Recommendations\Facades;

use Illuminate\Support\Facades\Facade;

class Recommendation extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * Resolves to:
     * - DMA\Recommendations\Classes\RecommendationManager
     *
     * @return string
     */
    protected static function getFacadeAccessor(){ 
        return 'recommendations';
    }
}