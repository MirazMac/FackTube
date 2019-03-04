<?php

namespace MirazMac\FackTube;

/**
* Generic helper functions wrapped in a class, because apparently if you write
* functions outside of a class nowadays then you're a monster
*
* @author MirazMac <mirazmac@gmail.com>
* @version 0.1 Initial
* @license LICENSE The MIT License
* @link https://mirazmac.com/ Author Homepage
*/
class Helpers
{
    /**
     * Strips everything but numbers from a string
     *
     * @param  string $string
     * @return integer
     */
    public static function numbersOnly($string)
    {
        return (int) preg_replace('/[^0-9]/i', '', $string);
    }
}
