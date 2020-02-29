<?php

namespace khrystonko\OpenDotaBundle\Controller;

use khrystonko\OpenDotaBundle\Entity\OpendotaInterface;

class HeroController
{
    private $openDotaService;

    public function __construct(
        OpendotaInterface $openDotaService
    ) {
        $this->openDotaService = $openDotaService;
    }

    /**
     * @Route("/heroes")
     */
    public function index()
    {
        $heroes = $this->openDotaService->heroes();

        return $this->render('hero\list.html.twig', [
            'heroes' => $heroes,
        ]);
    }
}