<?php

namespace App\Http\Controllers\Business\Dashboard;

use App\Http\Controllers\Controller;
use App\Support\HelpFaq;

/** The Help & FAQ page: the questions this team member can use. */
class HelpController extends Controller
{
    public function index(HelpFaq $faq)
    {
        return view('pages.business.faq.index', [
            'sections' => $faq->sectionsFor(auth()->user()),
            'supportEmail' => config('app.support_email'),
        ]);
    }
}
