<?php

declare(strict_types=1);

namespace App\Portal\Domain\Help;

interface FaqRepository
{
    /** La ayuda cargada, o null si todavía no se ha cargado nunca. */
    public function current(): ?Faq;

    public function save(Faq $faq): void;
}
