<?php namespace DMA\Recommendations\Traits;

trait StringUtils {
    
    /**
     * Helper for normalize strings, specially when looking up for methods
     * @param string $string
     * @param boolean $capitalizeFirstCharacter
     * @return string
     */
    protected function underscoreToCamelCase($string, $capitalizeFirstCharacter = false)
    {
    
        $str = str_replace(' ', '', ucwords(str_replace('_', ' ', $string)));
    
        if (!$capitalizeFirstCharacter) {
            $str[0] = strtolower($str[0]);
        }
    
        return $str;
    }
    
    /**
     * Normalize whitespace in a string
     * useful when comparing strings
     * @param string $string
     * @return $string
     */
    public function normalizeWhiteSpace($string)
    {
        // Remove leading and ending white spaces
        $string = trim($string);
    
        // Normalize white spaces
        $string = preg_replace('/[ ]{2,}/i', ' ', $string);
        return $string;
    }    
    
}