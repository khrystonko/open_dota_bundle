<?php

namespace khrystonko\OpenDotaBundle\Entity;

interface OpendotaInterface
{
    public function heroes();
    public function heroMatches(int $hero_id);
}