<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Message;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Project;
use App\Models\User;
use App\Services\ClientContext;
use App\Services\Mentions;
use App\Services\Notifier;
use App\Services\ReplyAddress;
use App\Support\Tenancy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Receives a forwarded email from an inbound email service and posts it as a message.
 * Works with Mailgun, Postmark and SendGrid field names, or plain to/from/text.
 */
class InboundEmailController extends Controller
{
    public function __invoke(Request $request, Tenancy $tenancy, ClientContext $ctx, Notifier $notifier): JsonResponse
    {
        $secret = (string) config('workora.inbound_secret');
        $given = (string) ($request->header('X-Inbound-Secret') ?: $request->query('secret'));
        abort_unless($secret !== '' && hash_equals($secret, $given), 403);

        $to = (string) ($request->input('OriginalRecipient') ?: $request->input('recipient') ?: $request->input('to') ?: $request->input('To'));
        $from = (string) ($request->input('From') ?: $request->input('sender') ?: $request->input('from'));
        $text = (string) ($request->input('StrippedTextReply') ?: $request->input('stripped-text') ?: $request->input('text') ?: $request->input('TextBody') ?: $request->input('body-plain'));

        $parsed = ReplyAddress::parse($to);
        if (! $parsed) {
            return response()->json(['ok' => false, 'reason' => 'unknown address'], 200);   // 200 so the service does not retry forever
        }
        [$userId, $clientId, $projectId] = $parsed;

        $body = ReplyAddress::stripQuoted($text);
        $user = User::query()->find($userId);
        $client = Client::withoutGlobalScopes()->find($clientId);
        // The sender must be the person the address was made for.
        if (! $user || ! $client || $body === '' || ! preg_match('/'.preg_quote(strtolower($user->email), '/').'/', strtolower($from))) {
            return response()->json(['ok' => false, 'reason' => 'rejected'], 200);
        }
        $org = Organization::query()->find($client->organization_id);
        $member = $org ? OrganizationMember::withoutGlobalScopes()->where('organization_id', $org->id)->where('user_id', $user->id)->where('status', 'active')->first() : null;
        if (! $member) {
            return response()->json(['ok' => false, 'reason' => 'rejected'], 200);
        }
        $isClient = $member->role->isClient();
        if ($isClient && $member->client_id !== $client->id) {
            return response()->json(['ok' => false, 'reason' => 'rejected'], 200);
        }

        $tenancy->forOrganization($org, function () use ($client, $projectId, $user, $body, $isClient, $ctx, $notifier) {
            $project = $projectId ? Project::query()->where('client_id', $client->id)->find($projectId) : null;
            $message = Message::create(['client_id' => $client->id, 'project_id' => $project?->id, 'user_id' => $user->id, 'body' => mb_substr($body, 0, 5000)]);
            AuditLog::record('message.sent', $message, ['project_id' => $project?->id, 'client_id' => $client->id, 'via' => 'email']);

            $audience = $isClient ? $ctx->staffToNotify() : $ctx->clientUsers($client);
            $where = $project ? $client->name.' / '.$project->name : $client->name;
            $url = $isClient ? route('portal.messages.index', array_filter(['project' => $project?->id])) : route('messages.index', array_filter(['client' => $client->id, 'project' => $project?->id]));
            $cid = $client->id;
            $pid = $project?->id;
            $notifier->send($audience, 'message', $user->name.' replied by email', $where.': '.str($body)->limit(120), $url, $user, fn ($u) => ReplyAddress::make($u->id, $cid, $pid));
            app(Mentions::class)->notify($body, $audience->concat($ctx->staffToNotify()), $user, $where, $url);
        });

        return response()->json(['ok' => true]);
    }
}
