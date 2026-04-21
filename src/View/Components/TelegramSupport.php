<?php

namespace MoMagdy\TelegramSupport\View\Components;

use Illuminate\View\Component;

class TelegramSupport extends Component
{
    public function __construct(
        public string $height    = '100vh',
        public string $minHeight = '500px',
    ) {}

    public function render()
    {
        return view('telegram-support::components.telegram-support');
    }
}
