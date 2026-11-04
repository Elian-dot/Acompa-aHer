<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\PrepareSosRequest;
use App\Models\EmergencyContact;
use Illuminate\Http\JsonResponse;

class SosController extends Controller
{
    public function store(PrepareSosRequest $request): JsonResponse
    {
        $data = $request->validated();
        $contacts = $request->user()->emergencyContacts()->whereKey($data['contact_ids'])->orderBy('id')->get();
        abort_unless($contacts->count() === count($data['contact_ids']), 404);
        $message = 'Necesito ayuda.';
        if (isset($data['latitude'], $data['longitude'])) {
            $message .= sprintf(' Esta es mi ubicación aproximada: https://www.openstreetmap.org/?mlat=%.6F&mlon=%.6F#map=17/%.6F/%.6F', $data['latitude'], $data['longitude'], $data['latitude'], $data['longitude']);
        } else {
            $message .= ' No pude obtener mi ubicación.';
        }

        return response()->json([
            'data' => $contacts->map(fn (EmergencyContact $contact): array => [
                'id' => $contact->id, 'name' => $contact->name, 'phone' => $contact->phone,
                'call_url' => 'tel:'.$contact->phone,
                'sms_url' => 'sms:'.$contact->phone.'?body='.rawurlencode($message),
                'status' => 'prepared',
            ]),
            'message' => $message,
            'notice' => 'No se ha enviado ningún aviso. Abre el SMS y confirma su envío en tu dispositivo. El servidor no puede comprobar su entrega.',
        ]);
    }
}
