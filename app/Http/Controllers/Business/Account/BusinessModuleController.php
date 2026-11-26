<?php

namespace App\Http\Controllers\Business\Account;

use App\Http\Controllers\Controller;
use App\Services\Access\ModuleService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Lets a business owner switch self-managed optional modules (currently Listings)
 * on or off for their own business. The installation switch always takes precedence.
 */
class BusinessModuleController extends Controller
{
    public function update(Request $request, ModuleService $modules)
    {
        $user = $request->user();
        abort_if($user->is_sub_account, 403, 'Only the account owner can change modules.');

        $managed = array_keys(array_filter(ModuleService::MODULES, fn ($definition) => $definition['business_managed']));
        $data = $request->validate([
            'module' => ['required', Rule::in($managed)],
            'enabled' => ['required', 'boolean'],
        ]);

        if (!$modules->installationEnabled($data['module'])) {
            return response()->json([
                'success' => false,
                'message' => ModuleService::MODULES[$data['module']]['label'] . ' is not available on this platform.',
            ], 422);
        }

        $enabled = (bool) $data['enabled'];
        // "On" removes the override so the business follows the installation switch.
        $modules->setForBusiness($user->id, $data['module'], $enabled ? null : false);

        return response()->json([
            'success' => true,
            'enabled' => $enabled,
            'message' => ModuleService::MODULES[$data['module']]['label'] . ($enabled ? ' switched on.' : ' switched off.'),
        ]);
    }
}
