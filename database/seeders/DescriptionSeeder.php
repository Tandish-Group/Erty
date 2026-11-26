<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Longer texts shown in the app: the support guidelines (Support) and what deleting an account means
 * (Account settings). Edit them in the descriptions table to suit your installation; running this seeder
 * again only adds texts that are missing, so your edits are kept.
 */
class DescriptionSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->texts() as $type => $html) {
            if (!DB::table('descriptions')->where('type', $type)->exists()) {
                DB::table('descriptions')->insert([
                    'type' => $type, 'allocation' => 'Platform', 'section' => 1, 'description' => $html,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
    }

    private function texts(): array
    {
        return [
            'support_guidelines' => <<<'HTML'
<p>Support tickets go to the team that runs this installation of Erty. These guidelines help them answer quickly.</p>
<h4>Every ticket</h4>
<ul>
<li><strong>One issue per ticket.</strong> Follow up on an open ticket instead of creating a new one.</li>
<li><strong>Say where it happened.</strong> Name the page or feature, for example "Tenants → a tenancy → Account".</li>
<li><strong>Say what you did and what you saw.</strong> The steps, the message on screen, and what you expected instead. A screenshot helps.</li>
<li><strong>Keep secrets out.</strong> Never include passwords, sign-in codes or full payment card numbers.</li>
<li><strong>Be courteous.</strong> Abusive or threatening messages are not answered.</li>
</ul>
<h4>By topic</h4>
<ul>
<li><strong>Rent and payments:</strong> include the tenancy reference, the invoice number and the payment date and reference.</li>
<li><strong>Properties, units and listings:</strong> include the property name and the unit number.</li>
<li><strong>Leads, agents and commission:</strong> include the lead or agent name and the period you are looking at.</li>
<li><strong>Sign-in and security:</strong> report anything suspicious straight away, and say when it happened.</li>
<li><strong>Bugs and errors:</strong> include your browser and device, and the time it happened.</li>
</ul>
HTML,
            'delete_policy' => <<<'HTML'
<p><strong>Deleting your account</strong> stops you signing in straight away and queues your personal details (name, contact details, preferences) for permanent removal.</p>
<p>Business records you created, such as properties, tenancies, payments and documents, stay with the business so its accounts and history remain complete.</p>
<p><strong>This cannot be undone.</strong> Removal from backups can take a few days. Download anything you need first, and ask the team running this installation if you have questions.</p>
HTML,
        ];
    }
}
