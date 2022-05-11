<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Event;
use App\Traits\ResponseTrait;
use App\Traits\AuthTrait;
use Illuminate\Support\Facades\Log;

class EventController extends Controller
{
    use ResponseTrait, AuthTrait;

    public function getEvents() {
        Log::info("Entering EventController getEvents...");

        try {
            $events = Event::with('user:id,first_name,last_name,username')->get();

            if (count($events) > 0) {
                Log::info("Successfully retrieved events. Leaving EventController getEvents...");

                foreach ($events as $event) {
                    $event->details = substr($event->details, 0, 50);
                }

                return $this->successResponse('details', $events);
            } else {
                Log::error("No events yet. No action needed.\n");

                return $this->errorResponse("No events yet.");
            }
        } catch (\Exception $e) {
            Log::error("Failed to retrieve events. " . $e->getMessage() . ".\n");

            return $this->errorResponse($this->getPredefinedResponse('default', null));
        }
    }
}
