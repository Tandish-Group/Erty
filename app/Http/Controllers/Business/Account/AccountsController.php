<?php

namespace App\Http\Controllers\Business\Account;

use App\Models\User;
use App\Models\Listing;
use App\Models\Business;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use App\Models\BusinessPaymentMethod;

class AccountsController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }
    
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $user_id = business_owner_id();

        $user = User::join('businesses','users.id','businesses.user_id')
        ->select('businesses.*')->where('users.id', $user_id)->first();

        $canEditBusiness = auth()->user()->hasBusinessPermission('account_settings', 'edit');

        return view('pages.business.account.index', compact('user', 'canEditBusiness'));
    }
    
    
    

    /**
     * Reset user password.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function passwordReset(Request $request)
    {
        // Validate the request data
        $validator = Validator::make($request->all(), [
            'currentpassword' => 'required|string|min:8',
            'newpassword' => 'required|string|min:8|confirmed',
        ]);

         // Check if validation fails
        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }
    
        try {
            // Retrieve the user
            // Always the signed-in person: staff and owners each manage their own sign-in details.
            $user = auth()->user();
    
            // Check if the current password matches
            if (!Hash::check($request->input('currentpassword'), $user->password)) {
                return redirect()->back()->with('error', 'The current password you entered was wrong. Please try again.');
            }
    
            // Check if the new password is the same as the current password
            if (Hash::check($request->input('newpassword'), $user->password)) {
                return redirect()->back()->with('error', 'New Password cannot be the same as the current password. Please choose a different password.');
            }
    
            // Update the user's password
            $user->password = Hash::make($request->input('newpassword'));
            $user->save();
    
            return redirect()->back()->with('success', 'Password was reset successfully.');
        } catch (\Exception $e) {
            // Log the error for debugging
            Log::error('Error resetting password: ' . $e->getMessage());
    
            return redirect()->back()->with('error', 'An error occurred while resetting the password. Please try again later.');
        }
    }

    /**
     * Reset user email.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function emailReset(Request $request)
    {
        // Validate the request data
        $validator = Validator::make($request->all(), [
            'emailaddress' => 'required|email|max:255|unique:users,email,' . auth()->id(),
            'confirmemailpassword' => 'required|string|min:8',
        ]);

        // Check if validation fails
        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        try {
            // Retrieve the user
            // Always the signed-in person: staff and owners each manage their own sign-in details.
            $user = auth()->user();

            // Check if the provided password matches the user's current password
            if (!Hash::check($request->input('confirmemailpassword'), $user->password)) {
                return redirect()->back()->with('error', 'You have provided an incorrect password. Please try again.');
            }

            // Update the user's email
            $user->email = $request->input('emailaddress');
            $user->save();

            return redirect()->back()->with('success', 'Email was updated successfully.');
        } catch (\Exception $e) {
            // Log the error for debugging
            Log::error('Error updating email: ' . $e->getMessage());

            return redirect()->back()->with('error', 'An error occurred while updating the email. Please try again later.');
        }
    }


    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Account  $account
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $slug)
    {
        // Business details for everyone on the team. Personal details are saved by ProfileController.
        $business = Business::where('user_id', business_owner_id())->firstOrFail();
        $request->validate([
            'business_email' => 'required|email|max:255',
            'business_description' => 'required|string|max:240',
            'website' => $business->account_type === 'agency' ? 'required|url|max:255' : 'nullable',
        ]);

        $this->updateBusiness($business, $request);

        return redirect()->to(url('/business_account'))->with('success', 'Business details were updated.');
    }
    
    
    protected function updateBusiness(Business $business, $request)
    {
        $business->update([
            'business_description' => $request->input('business_description'),
            'business_site' => ($business->account_type == 'agency') ? $request->input('website') : null,
            'business_email' => $request->input('business_email'),
        ]);
    }
    
    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Account  $account
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request)
    {
        return DB::transaction(function () use ($request) {
            try {
                // Validate the email input
                $user = $this->getAuthenticatedUser();
                $this->validateEmail($request->input('email'), $user->email);
    
                $this->deleteUserData($user);
    
                return redirect('/');
                
            } catch (\Exception $e) {
                return redirect()->back()->with('error', 'There was an error deleting the user and associated data: ' . $e->getMessage());
            }
        });
    }

    /**
     * Get the authenticated user.
     */
    protected function getAuthenticatedUser()
    {
        return auth()->user();
    }
    
    /*
    * Validate User Email
    */
    protected function validateEmail($inputEmail, $userEmail)
    {
        if ($inputEmail != $userEmail) {
            throw new \Exception("You have provided an incorrect email. Please try again.");
        }
    }
    
    /*
    * Stage for Deletion
    */
    protected function deleteUserData($user)
    {
        try {
            DB::transaction(function () use ($user) {
                
                // Make their listings unavailable
                Listing::where('user_id', $user->id)->update(['availability' => 'Unavailable', 'status' => 'Archived']);

                $user->delete_account = true;                
                // Staged for deletion
                $user->save();
                
                Log::info("User account {$user->id} staged for deletion");
            });
        } catch (\Exception $e) {
            Log::error('Error during user account deletion: ' . $e->getMessage());
            throw $e;
        }
    }

    // ─── Payment Methods ──────────────────────────────────────────────────

    /**
     * List all payment methods for the authenticated user.
     */
    public function paymentMethodsList()
    {
        $methods = BusinessPaymentMethod::where('user_id', business_owner_id())
            ->orderByDesc('is_primary')
            ->orderBy('created_at')
            ->get()
            ->map(function ($m) {
                return [
                    'id'          => $m->id,
                    'method_type' => $m->method_type,
                    'type_label'  => $m->type_label,
                    'label'       => $m->label,
                    'details'     => $m->details,
                    'is_primary'  => $m->is_primary,
                    'is_active'   => $m->is_active,
                ];
            });

        return response()->json(['status' => 200, 'data' => $methods]);
    }

    /**
     * Store a new payment method.
     */
    public function storePaymentMethod(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'method_type' => 'required|in:mpesa_paybill,mpesa_till,mpesa_send_money,bank_transfer,cash',
            'label'       => 'required|string|max:100',
            'details'     => 'required|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 422, 'message' => $validator->errors()->first()], 422);
        }

        try {
            $userId = business_owner_id();

            // If setting as primary, clear existing primary
            if ($request->boolean('is_primary')) {
                BusinessPaymentMethod::where('user_id', $userId)->update(['is_primary' => false]);
            }

            $method = BusinessPaymentMethod::create([
                'user_id'     => $userId,
                'method_type' => $request->method_type,
                'label'       => $request->label,
                'details'     => $request->details,
                'is_primary'  => $request->boolean('is_primary'),
                'is_active'   => true,
            ]);

            Log::info("Payment method #{$method->id} created for user {$userId}");

            return response()->json([
                'status'  => 200,
                'message' => 'Payment method added successfully.',
                'data'    => [
                    'id'          => $method->id,
                    'method_type' => $method->method_type,
                    'type_label'  => $method->type_label,
                    'label'       => $method->label,
                    'details'     => $method->details,
                    'is_primary'  => $method->is_primary,
                    'is_active'   => $method->is_active,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Error creating payment method: ' . $e->getMessage());
            return response()->json(['status' => 500, 'message' => 'Failed to add payment method.'], 500);
        }
    }

    /**
     * Update an existing payment method.
     */
    public function updatePaymentMethod(Request $request, $id)
    {
        $method = BusinessPaymentMethod::where('user_id', business_owner_id())->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'method_type' => 'required|in:mpesa_paybill,mpesa_till,mpesa_send_money,bank_transfer,cash',
            'label'       => 'required|string|max:100',
            'details'     => 'required|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 422, 'message' => $validator->errors()->first()], 422);
        }

        try {
            $userId = business_owner_id();

            if ($request->boolean('is_primary')) {
                BusinessPaymentMethod::where('user_id', $userId)->where('id', '!=', $id)->update(['is_primary' => false]);
            }

            $method->update([
                'method_type' => $request->method_type,
                'label'       => $request->label,
                'details'     => $request->details,
                'is_primary'  => $request->boolean('is_primary'),
            ]);

            return response()->json([
                'status'  => 200,
                'message' => 'Payment method updated.',
                'data'    => [
                    'id'          => $method->id,
                    'method_type' => $method->method_type,
                    'type_label'  => $method->type_label,
                    'label'       => $method->label,
                    'details'     => $method->details,
                    'is_primary'  => $method->is_primary,
                    'is_active'   => $method->is_active,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Error updating payment method: ' . $e->getMessage());
            return response()->json(['status' => 500, 'message' => 'Failed to update payment method.'], 500);
        }
    }

    /**
     * Delete a payment method.
     */
    public function destroyPaymentMethod($id)
    {
        $method = BusinessPaymentMethod::where('user_id', business_owner_id())->findOrFail($id);

        try {
            $method->delete();
            return response()->json(['status' => 200, 'message' => 'Payment method removed.']);
        } catch (\Exception $e) {
            Log::error('Error deleting payment method: ' . $e->getMessage());
            return response()->json(['status' => 500, 'message' => 'Failed to remove payment method.'], 500);
        }
    }

    /**
     * Set a payment method as primary (clears all others).
     */
    public function setPrimaryPaymentMethod($id)
    {
        $userId = business_owner_id();
        $method = BusinessPaymentMethod::where('user_id', $userId)->findOrFail($id);

        try {
            BusinessPaymentMethod::where('user_id', $userId)->update(['is_primary' => false]);
            $method->update(['is_primary' => true]);

            return response()->json(['status' => 200, 'message' => 'Primary payment method updated.']);
        } catch (\Exception $e) {
            Log::error('Error setting primary payment method: ' . $e->getMessage());
            return response()->json(['status' => 500, 'message' => 'Failed to set primary.'], 500);
        }
    }

    /**
     * Toggle active/inactive status of a payment method.
     */
    public function togglePaymentMethod($id)
    {
        $method = BusinessPaymentMethod::where('user_id', business_owner_id())->findOrFail($id);

        try {
            $method->update(['is_active' => !$method->is_active]);
            $label = $method->is_active ? 'enabled' : 'disabled';

            return response()->json(['status' => 200, 'message' => "Payment method {$label}.", 'is_active' => $method->is_active]);
        } catch (\Exception $e) {
            Log::error('Error toggling payment method: ' . $e->getMessage());
            return response()->json(['status' => 500, 'message' => 'Failed to toggle payment method.'], 500);
        }
    }

}
