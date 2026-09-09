<?php

namespace Webkul\Shop\Http\Controllers\API;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Webkul\Shipping\Carriers\DhlShipmentService;

class CoreController extends APIController
{
    /**
     * Get countries.
     *
     * @return JsonResponse
     */
    public function getCountries()
    {
        return response()->json([
            'data' => core()->countries()->map(fn ($country) => [
                'id' => $country->id,
                'code' => $country->code,
                'name' => $country->name,
            ]),
        ]);
    }

    /**
     * Get states.
     *
     * @return JsonResponse
     */
    public function getStates()
    {
        return response()->json([
            'data' => core()->groupedStatesByCountries(),
        ]);
    }

    /**
     * Search cities the shipping carrier actually serves, for the address
     * form's city typeahead. Restricting the field to these means a
     * customer can't save a destination that later turns out to have no
     * shipping option.
     *
     * @return JsonResponse
     */
    public function searchCities(Request $request, DhlShipmentService $dhlShipmentService)
    {
        $this->validate($request, [
            'query' => 'required|string|min:2|max:60',
            'country' => 'required|string|size:2',
        ]);

        return response()->json([
            'data' => $dhlShipmentService->searchCities(
                $request->input('query'),
                $request->input('country')
            ),
        ]);
    }
}
