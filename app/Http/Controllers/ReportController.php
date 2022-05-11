<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Report;
use App\Traits\ResponseTrait;
use App\Traits\AuthTrait;
use Illuminate\Support\Facades\Log;

class ReportController extends Controller
{
    use ResponseTrait, AuthTrait;

    public function getReports() {
        Log::info("Entering ReportController getReports...");

        try {
            $reports = Report::with('user:id,first_name,last_name,username')->get();

            if (count($reports) > 0) {
                Log::info("Successfully retrieved reports. Leaving ReportController getReports...");

                return $this->successResponse('details', $reports);
            } else {
                Log::error("No reports yet. No action needed.\n");

                return $this->errorResponse("No reports yet.");
            }
        } catch (\Exception $e) {
            Log::error("Failed to retrieve reports. " . $e->getMessage() . ".\n");

            return $this->errorResponse($this->getPredefinedResponse('default', null));
        }
    }
}
