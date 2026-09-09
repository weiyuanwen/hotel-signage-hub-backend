<?php

namespace App\Observers;

use App\Domains\Content\WelcomeTemplateCatalog;
use App\Models\Hotel;

class HotelObserver
{
    public function created(Hotel $hotel): void
    {
        app(WelcomeTemplateCatalog::class)->syncHotel($hotel);
    }
}
