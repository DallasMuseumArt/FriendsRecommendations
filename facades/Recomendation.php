<?php namespace DMA\Recomendations\Facades;

use Illuminate\Support\Facades\Facade;

class Recomendation extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * Resolves to:
     * - DMA\Recomendations\Classes\RecomendationManager
     *
     * @return string
     */
    protected static function getFacadeAccessor(){ 
        return 'recomendations';
    }
}