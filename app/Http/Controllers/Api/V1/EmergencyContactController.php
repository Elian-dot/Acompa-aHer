<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveEmergencyContactRequest;
use App\Http\Resources\EmergencyContactResource;
use App\Models\EmergencyContact;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class EmergencyContactController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', EmergencyContact::class);

        return EmergencyContactResource::collection($request->user()->emergencyContacts()->orderBy('id')->get());
    }

    public function store(SaveEmergencyContactRequest $request): EmergencyContactResource
    {
        $contact = DB::transaction(function () use ($request): EmergencyContact {
            $user = User::query()->whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            if ($user->emergencyContacts()->count() >= 20) {
                throw ValidationException::withMessages(['name' => 'Puedes guardar hasta 20 contactos de emergencia.']);
            }

            return $user->emergencyContacts()->create($request->safe()->only(['name', 'phone', 'email']));
        });

        return new EmergencyContactResource($contact);
    }

    public function update(SaveEmergencyContactRequest $request, EmergencyContact $contact): EmergencyContactResource
    {
        $contact->update($request->safe()->only(['name', 'phone', 'email']));

        return new EmergencyContactResource($contact);
    }

    public function destroy(Request $request, EmergencyContact $contact): Response
    {
        Gate::authorize('delete', $contact);
        $contact->delete();

        return response()->noContent();
    }
}
