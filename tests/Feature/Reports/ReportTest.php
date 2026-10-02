<?php

namespace Tests\Feature\Reports;

use App\Enums\AccountStatus;
use App\Enums\ModerationReason;
use App\Enums\ModerationType;
use App\Enums\OrderStatus;
use App\Enums\ReportStatus;
use App\Models\Order;
use App\Models\Report;
use App\Models\User;
use App\Services\ModerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Orders\BuildsOrders;
use Tests\TestCase;

/**
 * Signalements entre utilisateurs (dans le cadre d'une commande) et traitement par les admins.
 */
class ReportTest extends TestCase
{
    use BuildsOrders;
    use RefreshDatabase;

    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrderWorld();
        // Commande livrée : client, entreprise et livreur y ont tous pris part.
        $this->order = $this->makeOrder(OrderStatus::Delivered, ['delivery_id' => $this->courier->id]);
    }

    private function report(User $reporter, User $reported, string $reason = 'retard', ?Order $order = null)
    {
        return $this->actingAs($reporter)
            ->from('/somewhere')
            ->post(route('reports.store', $order ?? $this->order), [
                'reported_user_id' => $reported->id,
                'reason' => $reason,
                'description' => 'Le livreur est arrivé avec une heure de retard sans prévenir.',
            ]);
    }

    // --- Qui peut signaler qui ---

    public function test_each_party_can_report_the_other_parties_of_the_order(): void
    {
        $pairs = [
            [$this->client, $this->owner],
            [$this->client, $this->courier],
            [$this->owner, $this->client],
            [$this->owner, $this->courier],
            [$this->courier, $this->client],
            [$this->courier, $this->owner],
        ];

        foreach ($pairs as [$reporter, $reported]) {
            $this->report($reporter, $reported)->assertRedirect('/somewhere')->assertSessionHas('success');
            $this->assertDatabaseHas('reports', [
                'reporter_id' => $reporter->id,
                'reported_user_id' => $reported->id,
                'order_id' => $this->order->id,
                'status' => 'open',
            ]);
        }

        $this->assertSame(6, Report::count());
    }

    public function test_nobody_outside_the_order_can_be_reported_or_report(): void
    {
        $stranger = User::factory()->create(['role' => 'client']);

        // Une partie ne peut pas signaler quelqu'un d'étranger à la commande, ni elle-même.
        $this->report($this->client, $stranger)->assertSessionHasErrors('reported_user_id');
        $this->report($this->client, $this->farCourier)->assertSessionHasErrors('reported_user_id');
        $this->report($this->client, $this->client)->assertSessionHasErrors('reported_user_id');
        $this->report($this->client, $this->admin)->assertSessionHasErrors('reported_user_id');

        // Une personne étrangère à la commande ne peut rien signaler.
        $this->report($stranger, $this->owner)->assertForbidden();
        $this->report($this->farCourier, $this->client)->assertForbidden();
        $this->report($this->admin, $this->client)->assertForbidden();

        // Sans livreur assigné : le client ne peut signaler que le commerce.
        $pending = $this->makeOrder(OrderStatus::Pending);
        $this->report($this->client, $this->courier, order: $pending)->assertSessionHasErrors('reported_user_id');
        $this->report($this->client, $this->owner, order: $pending)->assertSessionHas('success');

        $this->assertSame(1, Report::count());
    }

    public function test_the_description_and_reason_are_required(): void
    {
        $this->actingAs($this->client)
            ->post(route('reports.store', $this->order), ['reported_user_id' => $this->courier->id, 'reason' => 'inconnu', 'description' => ''])
            ->assertSessionHasErrors(['reason', 'description']);

        $this->assertSame(0, Report::count());
    }

    public function test_a_duplicate_open_report_is_refused(): void
    {
        $this->report($this->client, $this->courier)->assertSessionHas('success');
        $this->report($this->client, $this->courier, 'fraude')->assertSessionHasErrors('reported_user_id');
        $this->assertSame(1, Report::count());

        // Le même signalant peut signaler l'autre partie ; un autre signalant, la même personne.
        $this->report($this->client, $this->owner)->assertSessionHas('success');
        $this->report($this->owner, $this->courier)->assertSessionHas('success');

        // Une fois le premier clos, un nouveau signalement est possible.
        Report::where('reporter_id', $this->client->id)->where('reported_user_id', $this->courier->id)->update(['status' => ReportStatus::Dismissed]);
        $this->report($this->client, $this->courier)->assertSessionHas('success');

        $this->assertSame(4, Report::count());
    }

    public function test_order_pages_offer_the_button_with_the_right_parties(): void
    {
        $this->actingAs($this->client)
            ->get(route('orders.show', $this->order))
            ->assertInertia(fn (Assert $page) => $page
                ->where('reporting.order_id', $this->order->id)
                ->where('reporting.parties.0.id', $this->owner->id)
                ->where('reporting.parties.0.label', 'Le commerce · Chez Maman Ngoye')
                ->where('reporting.parties.1.id', $this->courier->id)
                ->where('reporting.parties.1.label', 'Le livreur · Jean')
                ->has('reporting.reasons', 6));

        $this->report($this->owner, $this->client);
        $this->actingAs($this->owner)
            ->get(route('business.orders.show', $this->order))
            ->assertInertia(fn (Assert $page) => $page
                ->where('reporting.parties.0.id', $this->client->id)
                ->where('reporting.parties.0.already_reported', true)
                ->where('reporting.parties.1.id', $this->courier->id)
                ->where('reporting.parties.1.already_reported', false));

        $this->actingAs($this->courier)
            ->get('/delivery/history')
            ->assertInertia(fn (Assert $page) => $page
                ->where('deliveries.data.0.reporting.parties.0.id', $this->client->id)
                ->where('deliveries.data.0.reporting.parties.1.id', $this->owner->id));

        $current = $this->makeOrder(OrderStatus::Delivering, ['delivery_id' => $this->courier->id]);
        $this->actingAs($this->courier)
            ->get('/delivery/current')
            ->assertInertia(fn (Assert $page) => $page->where('reporting.order_id', $current->id)->has('reporting.parties', 2));
    }

    // --- Notifications et confidentialité ---

    public function test_admins_are_notified_and_the_reported_person_never_is(): void
    {
        $otherAdmin = User::factory()->create(['role' => 'admin']);
        $pendingAdmin = User::factory()->pending()->create(['role' => 'admin']);

        $this->report($this->client, $this->courier, 'fraude');

        $this->assertSame(['Nouveau signalement (urgent)'], $this->notificationTitles($this->admin));
        $this->assertSame(['Nouveau signalement (urgent)'], $this->notificationTitles($otherAdmin));
        $this->assertSame([], $this->notificationTitles($pendingAdmin));
        $this->assertSame([], $this->notificationTitles($this->courier));

        $report = Report::sole();
        $this->assertSame('/admin/reports/'.$report->id, $this->admin->notifications()->first()->data['url']);
    }

    public function test_the_reported_person_never_sees_the_reports_about_them(): void
    {
        $this->report($this->client, $this->courier);
        $report = Report::sole();

        // Aucune trace dans ses pages ni ses notifications.
        $this->actingAs($this->courier)
            ->get('/delivery/history')
            ->assertInertia(fn (Assert $page) => $page
                ->where('deliveries.data.0.reporting.parties.0.already_reported', false)
                ->missing('badges.open_reports'));
        $this->assertStringNotContainsString('signal', strtolower(json_encode($this->courier->notifications()->get()->pluck('data'))));

        // Ni la liste ni le détail des signalements.
        $this->actingAs($this->courier)->get('/admin/reports')->assertRedirect();
        $this->actingAs($this->courier)->get("/admin/reports/{$report->id}")->assertRedirect();

        // Une sanction lui parvient comme un avertissement ordinaire, sans mention du signalement.
        $this->actingAs($this->admin)->post(route('admin.reports.handle', $report), [
            'action' => 'warn',
            'message' => 'Merci de prévenir le client en cas de retard.',
        ]);
        $notifications = $this->courier->notifications()->get()->pluck('data');
        $this->assertSame(['Vous avez reçu un avertissement'], $notifications->pluck('title')->all());
        $this->assertStringNotContainsString('signal', strtolower(json_encode($notifications)));
        $this->assertStringNotContainsString($this->client->name, json_encode($notifications));
    }

    public function test_non_admins_cannot_reach_the_reports_space(): void
    {
        $this->report($this->client, $this->courier);
        $report = Report::sole();

        foreach ([$this->client, $this->owner, $this->courier] as $user) {
            $this->actingAs($user)->get('/admin/reports')->assertRedirect();
            $this->actingAs($user)->get("/admin/reports/{$report->id}")->assertRedirect();
            $this->actingAs($user)->post("/admin/reports/{$report->id}/handle", ['action' => 'dismiss'])->assertRedirect();
        }

        $pendingAdmin = User::factory()->pending()->create(['role' => 'admin']);
        $this->actingAs($pendingAdmin)->get('/admin/reports')->assertRedirect('/account/pending');

        $this->assertSame(ReportStatus::Open, $report->fresh()->status);
    }

    // --- Espace admin ---

    public function test_admin_list_puts_open_frauds_first_and_filters(): void
    {
        $this->report($this->client, $this->courier, 'retard');
        $this->report($this->owner, $this->client, 'fraude');
        $this->report($this->courier, $this->owner, 'absence');
        Report::where('reason', 'absence')->update(['status' => ReportStatus::Resolved]);

        $this->actingAs($this->admin)
            ->get('/admin/reports')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Reports/Index')
                ->where('filters.status', 'pending')
                ->has('reports.data', 2)
                ->where('reports.data.0.reason', 'fraude')
                ->where('reports.data.0.is_urgent', true)
                ->where('reports.data.1.reason', 'retard')
                ->where('reports.data.1.is_urgent', false)
                ->where('badges.open_reports', 2));

        $this->actingAs($this->admin)->get('/admin/reports?status=all')->assertInertia(fn (Assert $page) => $page->has('reports.data', 3));
        $this->actingAs($this->admin)->get('/admin/reports?status=resolved')->assertInertia(fn (Assert $page) => $page->has('reports.data', 1)->where('reports.data.0.reason', 'absence'));
        $this->actingAs($this->admin)->get('/admin/reports?status=all&reason=retard')->assertInertia(fn (Assert $page) => $page->has('reports.data', 1));
        $this->actingAs($this->admin)->get('/admin/reports?status=all&type=business')->assertInertia(fn (Assert $page) => $page->has('reports.data', 1)->where('reports.data.0.reported.id', $this->owner->id));
    }

    public function test_the_menu_counter_matches_pending_reports(): void
    {
        $this->actingAs($this->admin)->get('/admin')->assertInertia(fn (Assert $page) => $page->where('badges.open_reports', 0));

        $this->report($this->client, $this->courier);
        $this->report($this->client, $this->owner);
        $this->actingAs($this->admin)->get('/admin')->assertInertia(fn (Assert $page) => $page->where('badges.open_reports', 2));

        // Ouvert par un admin (en cours d'examen) : toujours à traiter.
        $first = Report::orderBy('id')->first();
        $this->actingAs($this->admin)->get("/admin/reports/{$first->id}");
        $this->assertSame(ReportStatus::InReview, $first->fresh()->status);
        $this->actingAs($this->admin)->get('/admin')->assertInertia(fn (Assert $page) => $page->where('badges.open_reports', 2));

        $this->actingAs($this->admin)->post(route('admin.reports.handle', $first), ['action' => 'dismiss']);
        $this->actingAs($this->admin)->get('/admin')->assertInertia(fn (Assert $page) => $page->where('badges.open_reports', 1));
    }

    public function test_admin_detail_shows_the_order_its_history_and_the_account_background(): void
    {
        $this->report($this->owner, $this->courier, 'retard');
        $older = Report::sole();
        app(ModerationService::class)->warn($this->admin, $this->courier, ModerationReason::RetardsRepetes, 'Soyez ponctuel, merci.');
        $this->order->recordStatus(OrderStatus::Delivered, $this->courier);
        $this->report($this->client, $this->courier, 'fraude');
        $report = Report::latest('id')->first();

        $this->actingAs($this->admin)
            ->get("/admin/reports/{$report->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Reports/Show')
                ->where('report.id', $report->id)
                ->where('report.is_urgent', true)
                ->where('report.status', 'in_review')
                ->where('report.can_handle', true)
                ->where('report.description', 'Le livreur est arrivé avec une heure de retard sans prévenir.')
                ->where('reportedAccount.id', $this->courier->id)
                ->where('reportedAccount.warnings_count', 1)
                ->where('order.number', $this->order->reference)
                ->where('order.history.0.label', 'Livrée')
                ->where('order.history.0.author', 'Jean Livreur')
                ->has('otherReports', 1)
                ->where('otherReports.0.id', $older->id)
                ->has('warnings', 1)
                ->where('warnings.0.message', 'Soyez ponctuel, merci.'));
    }

    public function test_admin_can_warn_block_or_dismiss_and_the_reporter_is_notified(): void
    {
        $this->report($this->client, $this->courier);
        $this->report($this->owner, $this->courier, 'comportement_irrespectueux');
        $this->report($this->courier, $this->client, 'absence');
        [$warn, $block, $dismiss] = Report::orderBy('id')->get()->all();

        // Avertissement (message obligatoire), via ModerationService.
        $this->actingAs($this->admin)->post(route('admin.reports.handle', $warn), ['action' => 'warn'])->assertSessionHasErrors('message');
        $this->actingAs($this->admin)->post(route('admin.reports.handle', $warn), [
            'action' => 'warn',
            'message' => 'Prévenez le client en cas de retard.',
            'admin_note' => 'Premier incident.',
        ])->assertSessionHas('success');
        $warn->refresh();
        $this->assertSame(ReportStatus::Resolved, $warn->status);
        $this->assertSame($this->admin->id, $warn->handled_by);
        $this->assertNotNull($warn->handled_at);
        $this->assertSame('Premier incident.', $warn->admin_note);
        $this->assertSame(1, $this->courier->moderationActions()->where('type', ModerationType::Warning)->where('reason', 'retards_repetes')->count());
        $this->assertSame(['Votre signalement a été traité'], $this->notificationTitles($this->client));

        // Blocage (durée obligatoire).
        $this->actingAs($this->admin)->post(route('admin.reports.handle', $block), ['action' => 'block', 'message' => 'Insultes envers le commerce.'])->assertSessionHasErrors('duration');
        $this->actingAs($this->admin)->post(route('admin.reports.handle', $block), [
            'action' => 'block',
            'message' => 'Insultes envers le commerce.',
            'duration' => '7d',
        ])->assertSessionHas('success');
        $this->assertSame(ReportStatus::Resolved, $block->fresh()->status);
        $this->assertSame(AccountStatus::Suspended, $this->courier->fresh()->account_status);
        $this->assertSame(['Votre signalement a été traité'], $this->notificationTitles($this->owner));
        // Le signalant ne connaît pas la sanction.
        $this->assertStringNotContainsString('bloqu', json_encode($this->owner->notifications()->first()->data));

        // Classement sans suite.
        $this->actingAs($this->admin)->post(route('admin.reports.handle', $dismiss), ['action' => 'dismiss'])->assertSessionHas('success');
        $this->assertSame(ReportStatus::Dismissed, $dismiss->fresh()->status);
        $this->assertSame(AccountStatus::Approved, $this->client->fresh()->account_status);
        $this->assertContains('Votre signalement a été traité', $this->notificationTitles($this->courier->fresh()));

        // Un signalement clos ne se traite pas deux fois.
        $this->actingAs($this->admin)->post(route('admin.reports.handle', $dismiss), ['action' => 'resolve'])->assertSessionHasErrors('report');
        $this->assertSame(ReportStatus::Dismissed, $dismiss->fresh()->status);
    }

    public function test_an_impossible_sanction_leaves_the_report_open(): void
    {
        $this->report($this->client, $this->courier);
        $report = Report::sole();
        $this->courier->update(['account_status' => AccountStatus::Suspended]);

        $this->actingAs($this->admin)->post(route('admin.reports.handle', $report), [
            'action' => 'block',
            'message' => 'Fraude au paiement.',
            'duration' => 'indefinite',
        ])->assertSessionHasErrors('moderation');

        $this->assertSame(ReportStatus::Open, $report->fresh()->status);
        $this->assertSame([], $this->notificationTitles($this->client));
    }
}
