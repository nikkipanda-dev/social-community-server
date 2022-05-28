<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Event;
use App\Models\EventReply;
use App\Models\EventReplyHeart;
use App\Models\EventParticipant;
use App\Models\User;
use App\Traits\ResponseTrait;
use App\Traits\AuthTrait;
use App\Traits\PostTrait;
use Illuminate\Support\Facades\Log;

class EventController extends Controller
{
    use ResponseTrait, AuthTrait, PostTrait;

    public function getEvents(Request $request) {
        Log::info("Entering EventController getEvents...");

        $this->validate($request, [
            'category' => 'bail|nullable|string|in:newest,oldest',
        ]);

        try {
            $events = $this->getAllEvents($request->category ? $request->category : '');

            if (count($events) === 0) {
                Log::notice("No events yet. No action needed.\n");

                return $this->errorResponse("No events yet.");
            }

            Log::info("Successfully retrieved events. Leaving EventController getEvents...");

            foreach ($events as $event) {
                unset($event->id);
                unset($event->user_id);
                unset($event->updated_at);
                unset($event->deleted_at);

                if ($event->user && $event->user->id) {
                    unset($event->user->id);
                }

                $event->details = substr($event->details, 0, 50);
            }

            return $this->successResponse('details', $events);
        } catch (\Exception $e) {
            Log::error("Failed to retrieve events. " . $e->getMessage() . ".\n");

            return $this->errorResponse($this->getPredefinedResponse('default', null));
        }
    }

    public function getPaginatedEvents(Request $request) {
        Log::info("Entering EventController getPaginatedEvents...");

        $this->validate($request, [
            'category' => 'bail|nullable|string|in:newest,oldest',
            'offset' => 'bail|required|numeric',
            'limit' => 'bail|required|numeric',
        ]);

        try {
            $entries = $this->getChunkedEvents($request->category ? $request->category : '', $request->offset, $request->limit);

            if (count($entries) === 0) {
                Log::notice("No additional events fetched. No action needed.\n");

                return $this->errorResponse("No additional events to show.");
            }

            foreach ($entries as $entry) {
                unset($entry->user_id);
                unset($entry->id);
                unset($entry->updated_at);
                unset($entry->deleted_at);
                if ($entry->user && $entry->user->id) {
                    unset($entry->user->id);
                }
            }

            Log::info("Successfully retrieved paginated events. Leaving EventController getPaginatedEvents...");

            return $this->successResponse('details', $entries);
        } catch (\Exception $e) {
            Log::error("Failed to retrieve paginated events. " . $e->getMessage() . ".\n");

            return $this->errorResponse($this->getPredefinedResponse('default', null));
        }
    }

    public function getEvent(Request $request) {
        Log::info("Entering EventController getEvent...");

        $this->validate($request, [
            'username' => 'bail|required|exists:users',
            'slug' => 'bail|required|string|exists:events',
        ]);

        try {
            if (!($this->hasAuthHeader($request->header('authorization')))) {
                Log::error("Failed to store event. Missing authorization header or does not match regex.\n");

                return $this->errorResponse($this->getPredefinedResponse('default', null));
            }

            $user = User::where('username', $request->username)->first();

            if (!($user)) {
                Log::error("Failed to retrieve event. User does not exist or might be deleted.\n");

                return $this->errorResponse($this->getPredefinedResponse('user not found', null));
            }

            foreach ($user->tokens as $token) {
                if ($token->tokenable_id === $user->id) {
                    $event = $this->getEventRecord($request->slug);

                    if (!$event) {
                        Log::error("Failed to retrieve event. Event does not exist or might be deleted.\n");

                        return $this->errorResponse("Event does not exist.");
                    }

                    unset($event->id);
                    unset($event->user_id);
                    unset($event->deleted_at);

                    if ($event->user && $event->user->id) {
                        unset($event->user->id);
                    }

                    Log::info("Successfully retrieved event ID " . $event->id . ". Leaving EventController getEvent...");

                    return $this->successResponse("details", $event);

                    break;
                }
            }
        } catch (\Exception $e) {
            Log::error("Failed to retrieve event. " . $e->getMessage() . ".\n");

            return $this->errorResponse($this->getPredefinedResponse('default', null));
        }
    }

    public function storeEvent(Request $request) {
        Log::info("Entering EventController storeEvent...");

        $this->validate($request, [
            'username' => 'bail|required|exists:users',
            'name' => 'bail|required|string|between:2,50',
            'body' => 'bail|required|string|between:2,10000',
            'start_date' => 'bail|required|date_format:Y-n-j|after:' . date("Y-m-d"),
            'end_date' => 'bail|required|date_format:Y-n-j|after_or_equal:'.$request->start_date,
            'start_date_time' => 'bail|required|date_format:H:i:s',
            'end_date_time' => 'bail|required|date_format:H:i:s',
            'rsvp_date' => 'bail|required|date_format:Y-n-j|before_or_equal:' . $request->start_date,
            'category' => 'bail|required|in:hobby,wellbeing,career,coaching,science_and_tech,social_cause',
        ]);

        try {
            if (!($this->hasAuthHeader($request->header('authorization')))) {
                Log::error("Failed to store new event. Missing authorization header or does not match regex.\n");

                return $this->errorResponse($this->getPredefinedResponse('default', null));
            }

            $user = User::where('username', $request->username)->first();

            if (!($user)) {
                Log::error("Failed to store new event. User does not exist or might be deleted.\n");

                return $this->errorResponse($this->getPredefinedResponse('user not found', null));
            }

            foreach ($user->tokens as $token) {
                if ($token->tokenable_id === $user->id) {
                    $isUnique = false;
                    $slug = null;

                    $start_datetime = date_create($request->start_date . " " . $request->start_date_time);
                    $end_datetime = date_create($request->end_date . " " . $request->end_date_time);
                    $rsvp_date = date_create($request->rsvp_date);

                    $event = new Event();

                    $event->user_id = $user->id;
                    $event->name = $request->name;
                    $event->start_datetime = date_format($start_datetime, 'Y-m-d H:i:s');
                    $event->end_datetime = date_format($end_datetime, 'Y-m-d H:i:s');
                    $event->rsvp_date = date_format($rsvp_date, 'Y-m-d H:i:s');
                    $event->details = $request->body;
                    $event->is_hobby = false;
                    $event->is_wellbeing = false;
                    $event->is_career = false;
                    $event->is_coaching = false;
                    $event->is_science_and_tech = false;
                    $event->is_social_cause = false;
                    $event->{'is_' . $request->category} = true;

                    do {
                        $slug = $this->generateSlug();

                        if (!(Event::where('slug', $slug)->first())) {
                            $isUnique = true;
                        }
                    } while (!($isUnique));

                    $event->slug = $slug;

                    $event->save();

                    if (!$event) {
                        Log::error("Failed to store new event.\n");

                        return $this->errorResponse($this->getPredefinedResponse('default', null));
                    }

                    Log::info("Successfully stored new event ID ".$event->id. ". Leaving EventController storeEvent...\n");

                    return $this->successResponse("details", $event);

                    break;
                }
            }
        } catch (\Exception $e) {
            Log::error("Failed to store new event. " . $e->getMessage() . ".\n");

            return $this->errorResponse($this->getPredefinedResponse('default', null));
        }
    }

    public function updateEvent(Request $request) {
        Log::info("Entering EventController updateEvent...");

        $this->validate($request, [
            'username' => 'bail|required|exists:users',
            'slug' => 'bail|required|string|exists:events',
            'name' => 'bail|nullable|string|between:2,50',
            'body' => 'bail|nullable|string|between:2,10000',
            'start_date' => 'bail|nullable|date_format:Y-n-j|after:' . date("Y-m-d"),
            'end_date' => 'bail|nullable|date_format:Y-n-j|after_or_equal:' . $request->start_date,
            'start_date_time' => 'bail|nullable|date_format:H:i:s',
            'end_date_time' => 'bail|nullable|date_format:H:i:s',
            'rsvp_date' => 'bail|nullable|date_format:Y-n-j|before_or_equal:' . $request->start_date,
            'category' => 'bail|nullable|in:hobby,wellbeing,career,coaching,science_and_tech,social_cause',
        ]);

        try {
            if (!($this->hasAuthHeader($request->header('authorization')))) {
                Log::error("Failed to update event. Missing authorization header or does not match regex.\n");

                return $this->errorResponse($this->getPredefinedResponse('default', null));
            }

            $user = User::where('username', $request->username)->first();

            if (!($user)) {
                Log::error("Failed to update event. User does not exist or might be deleted.\n");

                return $this->errorResponse($this->getPredefinedResponse('user not found', null));
            }

            foreach ($user->tokens as $token) {
                if ($token->tokenable_id === $user->id) {
                    $event = $this->getEventRecord($request->slug);

                    if (!($event)) {
                        Log::error("Failed to update event. Event does not exist or might be deleted.\n");

                        return $this->errorResponse("Event does not exist.");
                    }

                    if ($event->user_id !== $user->id) {
                        Log::error("Failed to update event. Authenticated user is not the author.\n");

                        return $this->errorResponse($this->getPredefinedResponse('default', null));
                    }

                    unset($event['category']);

                    $start_datetime = ($request->start_date) ? date_create($request->start_date . " " . $request->start_date_time) : null;
                    $end_datetime = ($request->end_date) ? date_create($request->end_date . " " . $request->end_date_time) : null;
                    $rsvp_date = ($request->rsvp_date) ? date_create($request->rsvp_date) : null;

                    $event->name = ($request->name) ? $request->name : $event->name;
                    $event->start_datetime = ($start_datetime) ? date_format($start_datetime, 'Y-m-d H:i:s') : $event->start_datetime;
                    $event->end_datetime = ($end_datetime) ? date_format($end_datetime, 'Y-m-d H:i:s') : $event->end_datetime;
                    $event->rsvp_date = ($rsvp_date) ? date_format($rsvp_date, 'Y-m-d H:i:s') : $event->rsvp_date;
                    $event->details = ($request->body) ? $request->body : $event->details;

                    if ($request->category) {
                        $event->is_hobby = false;
                        $event->is_wellbeing = false;
                        $event->is_career = false;
                        $event->is_coaching = false;
                        $event->is_science_and_tech = false;
                        $event->is_social_cause = false;
                        $event->{'is_' . $request->category} = true;
                    }

                    $event->save();

                    $event = $this->getEventRecord($request->slug);

                    unset($event->id);
                    unset($event->user_id);
                    unset($event->deleted_at);

                    if ($event->user && $event->user->id) {
                        unset($event->user->id);
                    }

                    Log::info("Successfully updated event ID " . $event->id . ". Leaving EventController updateEvent...\n");

                    return $this->successResponse("details", $event);

                    break;
                }
            }
        } catch (\Exception $e) {
            Log::error("Failed to update event. " . $e->getMessage() . ".\n");

            return $this->errorResponse($this->getPredefinedResponse('default', null));
        }
    }

    public function destroyEvent(Request $request) {
        Log::info("Entering EventController destroyEvent...");

        $this->validate($request, [
            'username' => 'bail|required|exists:users',
            'slug' => 'bail|required|string|exists:events',
        ]);

        try {
            if (!($this->hasAuthHeader($request->header('authorization')))) {
                Log::error("Failed to soft delete event. Missing authorization header or does not match regex.\n");

                return $this->errorResponse($this->getPredefinedResponse('default', null));
            }

            $user = User::where('username', $request->username)->first();

            if (!($user)) {
                Log::error("Failed to soft delete event. User does not exist or might be deleted.\n");

                return $this->errorResponse($this->getPredefinedResponse('user not found', null));
            }

            foreach ($user->tokens as $token) {
                if ($token->tokenable_id === $user->id) {
                    $event = $this->getEventRecord($request->slug);

                    if (!($event)) {
                        Log::error("Failed to soft delete event. Event does not exist or might be deleted.\n");

                        return $this->errorResponse("Event does not exist.");
                    }

                    $originalId = $event->getOriginal('id');

                    $event->delete();
                    
                    if (Event::find($originalId)) {
                        Log::error("Failed to soft delete event ID ".$originalId.".\n");

                        return $this->errorResponse($this->getPredefinedResponse('default', null));
                    }

                    Log::info("Successfully soft deleted event ID " . $originalId . ". Leaving EventController destroyEvent...\n");

                    return $this->successResponse("details", null);

                    break;
                }
            }
        } catch (\Exception $e) {
            Log::error("Failed to update event. " . $e->getMessage() . ".\n");

            return $this->errorResponse($this->getPredefinedResponse('default', null));
        }
    }

    public function getEventReplies(Request $request) {
        Log::info("Entering EventController getEventReplies...");

        $this->validate($request, [
            'username' => 'bail|required|exists:users',
            'slug' => 'bail|required|string|exists:events',
        ]);

        try {
            if (!($this->hasAuthHeader($request->header('authorization')))) {
                Log::error("Failed to retrieve event replies. Missing authorization header or does not match regex.\n");

                return $this->errorResponse($this->getPredefinedResponse('default', null));
            }

            $user = User::where('username', $request->username)->first();

            if (!($user)) {
                Log::error("Failed to retrieve event replies. User does not exist or might be deleted.\n");

                return $this->errorResponse($this->getPredefinedResponse('user not found', null));
            }

            foreach ($user->tokens as $token) {
                if ($token->tokenable_id === $user->id) {
                    $post = $this->getEventRecord($request->slug);

                    if (!($post)) {
                        Log::error("Failed to retrieve event replies. Event does not exist or might be deleted.\n");

                        return $this->errorResponse("Event does not exist.");
                    }

                    $replies = $this->getAllEventReplies($post->id);

                    if (count($replies) === 0) {
                        Log::notice("No replies for event ID " . $post->id . " yet.\n");

                        return $this->errorResponse("No replies yet.");
                    }

                    foreach ($replies as $reply) {
                        $reply['heartDetails'] = $this->getEventReplyHearts($reply->id, $user->id);
                        unset($reply->id);
                        unset($reply->blog_entry_id);
                        unset($reply->created_at);
                        unset($reply->updated_at);
                        unset($reply->deleted_at);
                        unset($reply->user_id);

                        if ($reply->user && $reply->user->id) {
                            unset($reply->user->id);
                        }
                    }

                    Log::info("Successfully retrieved event replies for ID " . $post->id . ". Leaving EventController getEventReplies...\n");

                    return $this->successResponse("details", $replies);

                    break;
                }
            }
        } catch (\Exception $e) {
            Log::error("Failed to retrieve event replies. " . $e->getMessage() . ".\n");

            return $this->errorResponse($this->getPredefinedResponse('default', null));
        }
    }

    public function getPaginatedEventReplies(Request $request) {
        Log::info("Entering EventController getPaginatedEventReplies...");

        $this->validate($request, [
            'username' => 'bail|required|exists:users',
            'slug' => 'bail|required|string|exists:events',
            'limit' => 'bail|required|numeric',
        ]);

        try {
            if (!($this->hasAuthHeader($request->header('authorization')))) {
                Log::error("Failed to retrieve paginated event replies. Missing authorization header or does not match regex.\n");

                return $this->errorResponse($this->getPredefinedResponse('default', null));
            }

            $user = User::where('username', $request->username)->first();

            if (!($user)) {
                Log::error("Failed to retrieve paginated event replies. User does not exist or might be deleted.\n");

                return $this->errorResponse($this->getPredefinedResponse('user not found', null));
            }

            foreach ($user->tokens as $token) {
                if ($token->tokenable_id === $user->id) {
                    $event = $this->getEventRecord($request->slug);

                    if (!($event)) {
                        Log::error("Failed to retrieve paginated event replies. Event does not exist or might be deleted.\n");

                        return $this->errorResponse("Event does not exist.");
                    }

                    $replies = $this->getChunkedEventReplies($event->id, $request->limit);

                    if (count($replies) === 0) {
                        Log::notice("No additional event replies for event ID " . $event->id . ". No action needed.\n");

                        return $this->errorResponse("No additional replies to show.");
                    }

                    foreach ($replies as $reply) {
                        $reply['heartDetails'] = $this->getEventReplyHearts($reply->id, $user->id);
                        unset($reply->id);
                        unset($reply->blog_entry_id);
                        unset($reply->created_at);
                        unset($reply->updated_at);
                        unset($reply->deleted_at);
                        unset($reply->user_id);

                        if ($reply->user && $reply->user->id) {
                            unset($reply->user->id);
                        }
                    }

                    Log::info("Successfully retrieved paginated event replies for ID " . $event->id . ". Leaving EventController getPaginatedEventReplies...\n");

                    return $this->successResponse("details", $replies);

                    break;
                }
            }
        } catch (\Exception $e) {
            Log::error("Failed to retrieve paginated event replies. " . $e->getMessage() . ".\n");

            return $this->errorResponse($this->getPredefinedResponse('default', null));
        }
    }

    public function storeEventReply(Request $request) {
        Log::info("Entering EventController storeEventReply...");

        $this->validate($request, [
            'username' => 'bail|required|exists:users',
            'slug' => 'bail|required|string|exists:events',
            'body' => 'bail|required|string|between:2,10000',
        ]);

        try {
            if (!($this->hasAuthHeader($request->header('authorization')))) {
                Log::error("Failed to store new event reply. Missing authorization header or does not match regex.\n");

                return $this->errorResponse($this->getPredefinedResponse('default', null));
            }

            $user = User::where('username', $request->username)->first();

            if (!($user)) {
                Log::error("Failed to store new event reply. User does not exist or might be deleted.\n");

                return $this->errorResponse($this->getPredefinedResponse('user not found', null));
            }

            foreach ($user->tokens as $token) {
                if ($token->tokenable_id === $user->id) {
                    $event = $this->getEventRecord($request->slug);

                    if (!$event) {
                        Log::error("Failed to store new event reply. Event does not exist or might be deleted.\n");

                        return $this->errorResponse("Event does not exist.");
                    }

                    $isUnique = false;
                    $slug = null;

                    $reply = new EventReply();

                    $reply->event_id = $event->id;
                    $reply->user_id = $user->id;
                    $reply->body = $request->body;

                    do {
                        $slug = $this->generateSlug();

                        if (!(EventReply::where('slug', $slug)->first())) {
                            $isUnique = true;
                        }
                    } while (!($isUnique));

                    $reply->slug = $slug;

                    $reply->save();

                    if (!($reply)) {
                        Log::error("Failed to store new event reply for ID " . $reply->id . ".\n");

                        return $this->errorResponse($this->getPredefinedResponse('default', null));
                    }

                    Log::info("Successfully stored new event reply for ID " . $reply->id . ". Leaving EventController storeEventReply...\n");

                    return $this->successResponse("details", null);

                    break;
                }
            }
        } catch (\Exception $e) {
            Log::error("Failed to store new event reply. " . $e->getMessage() . ".\n");

            return $this->errorResponse($this->getPredefinedResponse('default', null));
        }
    }

    public function updateEventHeart(Request $request) {
        Log::info("Entering EventController updateEventHeart...");

        $this->validate($request, [
            'username' => 'bail|required|exists:users',
            'slug' => 'bail|required|string|exists:event_replies',
        ]);

        try {
            if (!($this->hasAuthHeader($request->header('authorization')))) {
                Log::error("Failed to update event reply heart. Missing authorization header or does not match regex.\n");

                return $this->errorResponse($this->getPredefinedResponse('default', null));
            }

            $user = User::where('username', $request->username)->first();

            if (!($user)) {
                Log::error("Failed to update event reply heart. User does not exist or might be deleted.\n");

                return $this->errorResponse($this->getPredefinedResponse('user not found', null));
            }

            foreach ($user->tokens as $token) {
                if ($token->tokenable_id === $user->id) {
                    $reply = $this->getEventReplyRecord($request->slug);

                    if (!($reply)) {
                        Log::error("Failed to update event reply heart. Reply does not exist or might be deleted.\n");

                        return $this->errorResponse("Reply does not exist.");
                    }

                    $heart = EventReplyHeart::where('event_reply_id', $reply->id)
                                            ->where('user_id', $user->id)
                                            ->first();

                    if ($heart) {
                        $originalId = $heart->getOriginal('id');

                        $heart->delete();

                        if (EventReplyHeart::find($originalId)) {
                            Log::error("Failed to soft delete event reply heart.\n");

                            return $this->errorResponse($this->getPredefinedResponse('default', null));
                        }

                        Log::info("Successfully soft deleted event reply heart ID " . $originalId . ". Leaving EventController updateEventHeart...\n");
                    }

                    if (!($heart)) {
                        $heart = new EventReplyHeart();

                        $heart->event_reply_id = $reply->id;
                        $heart->user_id = $user->id;

                        $heart->save();

                        if (!($heart)) {
                            Log::error("Failed to store event reply heart for ID " . $reply->id . ".\n");

                            return $this->errorResponse($this->getPredefinedResponse('default', null));
                        }

                        Log::info("Successfully stored event reply heart ID " . $heart->id . ". Leaving EventController updateEventHeart...\n");
                    }

                    $heartDetails = $this->getEventReplyHearts($reply->id, $user->id);

                    return $this->successResponse("details", $heartDetails);

                    break;
                }
            }
        } catch (\Exception $e) {
            Log::error("Failed to update event reply heart. " . $e->getMessage() . ".\n");

            return $this->errorResponse($this->getPredefinedResponse('default', null));
        }
    }

    public function getEventParticipants(Request $request) {
        Log::info("Entering EventController getEventParticipants...");

        $this->validate($request, [
            'username' => 'bail|required|exists:users',
            'slug' => 'bail|required|string|exists:events',
        ]);

        try {
            if (!($this->hasAuthHeader($request->header('authorization')))) {
                Log::error("Failed to retrieve event participants. Missing authorization header or does not match regex.\n");

                return $this->errorResponse($this->getPredefinedResponse('default', null));
            }

            $user = User::where('username', $request->username)->first();

            if (!($user)) {
                Log::error("Failed to retrieve event participants. User does not exist or might be deleted.\n");

                return $this->errorResponse($this->getPredefinedResponse('user not found', null));
            }

            foreach ($user->tokens as $token) {
                if ($token->tokenable_id === $user->id) {
                    $event = $this->getEventRecord($request->slug);

                    if (!$event) {
                        Log::error("Failed to retrieve event participants. Event does not exist or might be deleted.\n");

                        return $this->errorResponse("Event does not exist.");
                    }

                    $participants = $this->getAllEventParticipants($event->id);

                    if (!$participants) {
                        Log::notice("Event ID " . $event->id . " has no participants yet. No action needed.\n");

                        return $this->errorResponse("No participants yet.");
                    }

                    $isParticipant = $this->isEventParticipant($event->id, $user->id);

                    Log::info("Successfully retrieved blog entry ID " . $event->id . "'s participants. Leaving EventController getEventParticipants...");

                    return $this->successResponse("details", [
                        'is_supporter' => $isParticipant,
                        'supporters' => $participants,
                    ]);

                    break;
                }
            }
        } catch (\Exception $e) {
            Log::error("Failed to retrieve event participants. " . $e->getMessage() . ".\n");

            return $this->errorResponse($this->getPredefinedResponse('default', null));
        }
    }

    public function storeEventParticipant(Request $request) {
        Log::info("Entering EventController storeEventParticipant...");

        $this->validate($request, [
            'username' => 'bail|required|exists:users',
            'slug' => 'bail|required|string|exists:events',
        ]);

        try {
            if (!($this->hasAuthHeader($request->header('authorization')))) {
                Log::error("Failed to store new event participant. Missing authorization header or does not match regex.\n");

                return $this->errorResponse($this->getPredefinedResponse('default', null));
            }

            $user = User::where('username', $request->username)->first();

            if (!($user)) {
                Log::error("Failed to store new event participant. User does not exist or might be deleted.\n");

                return $this->errorResponse($this->getPredefinedResponse('user not found', null));
            }

            foreach ($user->tokens as $token) {
                if ($token->tokenable_id === $user->id) {
                    $event = $this->getEventRecord($request->slug);

                    if (!$event) {
                        Log::error("Failed to store new event participant. Event does not exist or might be deleted.\n");

                        return $this->errorResponse("Event does not exist.");
                    }

                    $participant = new EventParticipant();

                    $participant->event_id = $event->id;
                    $participant->user_id = $user->id;

                    $participant->save();

                    if (!$participant) {
                        Log::error("Failed to store new event participant.\n");

                        return $this->errorResponse($this->getPredefinedResponse('default', null));
                    }

                    Log::info("Successfully stored event participant for ID " . $participant->id . ". Leaving EventController storeEventParticipant...\n");

                    return $this->successResponse("details", null);

                    break;
                }
            }
        } catch (\Exception $e) {
            Log::error("Failed to store event participant. " . $e->getMessage() . ".\n");

            return $this->errorResponse($this->getPredefinedResponse('default', null));
        }
    }

    public function destroyEventParticipant(Request $request) {
        Log::info("Entering EventController destroyEventParticipant...");

        $this->validate($request, [
            'username' => 'bail|required|exists:users',
            'slug' => 'bail|required|string|exists:events',
        ]);

        try {
            if (!($this->hasAuthHeader($request->header('authorization')))) {
                Log::error("Failed to soft delete event participant. Missing authorization header or does not match regex.\n");

                return $this->errorResponse($this->getPredefinedResponse('default', null));
            }

            $user = User::where('username', $request->username)->first();

            if (!($user)) {
                Log::error("Failed to soft delete event participant. User does not exist or might be deleted.\n");

                return $this->errorResponse($this->getPredefinedResponse('user not found', null));
            }

            foreach ($user->tokens as $token) {
                if ($token->tokenable_id === $user->id) {
                    $event = $this->getEventRecord($request->slug);

                    if (!$event) {
                        Log::error("Failed to soft delete event participant. Event does not exist or might be deleted.\n");

                        return $this->errorResponse("Event post does not exist.");
                    }

                    $participant = EventParticipant::where('event_id', $event->id)
                                                 ->where('user_id', $user->id)
                                                 ->first();

                    if (!$participant) {
                        Log::error("Failed to soft delete event participant. Participant does not exist or might be deleted.\n");

                        return $this->errorResponse($this->getPredefinedResponse('default', null));
                    }

                    $originalId = $participant->getOriginal('id');

                    $participant->delete();

                    if (EventParticipant::find($originalId)) {
                        Log::error("Failed to soft delete event participant ID " . $originalId . ".\n");

                        return $this->errorResponse($this->getPredefinedResponse('default', null));
                    }

                    Log::info("Successfully soft deleted event participant ID " . $originalId . ". Leaving EventController destroyEventParticipant...\n");

                    return $this->successResponse("details", null);

                    break;
                }
            }
        } catch (\Exception $e) {
            Log::error("Failed to soft delete event participant. " . $e->getMessage() . ".\n");

            return $this->errorResponse($this->getPredefinedResponse('default', null));
        }
    }
}
