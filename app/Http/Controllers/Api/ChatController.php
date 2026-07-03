<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Chat;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ChatController extends Controller
{
    /**
     * GET /api/chats — "Secure Messages" screen: contact, last message preview, timestamp, unread count
     */
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $chats = Chat::where('requester_id', $userId)
            ->orWhere('recipient_id', $userId)
            ->with(['requester:id,name,verification_level', 'recipient:id,name,verification_level'])
            ->withCount(['messages as unread_count' => function ($q) use ($userId) {
                $q->whereNull('read_at')->where('sender_id', '!=', $userId);
            }])
            ->with(['messages' => fn ($q) => $q->latest()->limit(1)])
            ->latest()
            ->get()
            ->map(function ($chat) use ($userId) {
                $otherUser = $chat->requester_id === $userId ? $chat->recipient : $chat->requester;
                $lastMessage = $chat->messages->first();

                return [
                    'id' => $chat->id,
                    'status' => $chat->status,
                    'contact' => $otherUser,
                    'last_message' => $lastMessage?->body ?? ($lastMessage?->image_path ? '📷 Photo' : null),
                    'last_message_at' => $lastMessage?->created_at,
                    'unread_count' => $chat->unread_count,
                ];
            });

        return response()->json($chats);
    }

    /**
     * POST /api/chats — Request Chat (by recipient SAFEE PIN)
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate(['safee_pin' => ['required', 'string']]);

        $recipient = User::where('safee_pin', $validated['safee_pin'])->first();

        if (! $recipient) {
            return response()->json(['message' => 'No member found with that PIN'], 404);
        }

        if ($recipient->id === $request->user()->id) {
            return response()->json(['message' => 'Cannot start a chat with yourself'], 422);
        }

        $chat = Chat::firstOrCreate([
            'requester_id' => $request->user()->id,
            'recipient_id' => $recipient->id,
        ], ['status' => 'requested']);

        return response()->json($chat, 201);
    }

    /**
     * POST /api/chats/{chat}/accept
     */
    public function accept(Request $request, Chat $chat): JsonResponse
    {
        $this->authorizeParticipant($request, $chat);

        $chat->update(['status' => 'accepted']);

        return response()->json(['message' => 'Chat accepted', 'chat' => $chat]);
    }

    /**
     * POST /api/chats/{chat}/decline
     */
    public function decline(Request $request, Chat $chat): JsonResponse
    {
        $this->authorizeParticipant($request, $chat);

        $chat->update(['status' => 'declined']);

        return response()->json(['message' => 'Chat declined']);
    }

    /**
     * GET /api/chats/{chat}/messages
     */
    public function messages(Request $request, Chat $chat): JsonResponse
    {
        $this->authorizeParticipant($request, $chat);

        return response()->json($chat->messages()->with('sender:id,name')->oldest()->get());
    }

    /**
     * POST /api/chats/{chat}/messages — send text and/or image
     */
    public function sendMessage(Request $request, Chat $chat): JsonResponse
    {
        $this->authorizeParticipant($request, $chat);

        if ($chat->status !== 'accepted') {
            return response()->json(['message' => 'Chat is not active'], 422);
        }

        $validated = $request->validate([
            'body' => ['required_without:image', 'nullable', 'string', 'max:2000'],
            'image' => ['required_without:body', 'nullable', 'image', 'max:8192'],
        ]);

        $message = $chat->messages()->create([
            'sender_id' => $request->user()->id,
            'body' => $validated['body'] ?? null,
            'image_path' => $request->hasFile('image')
                ? $request->file('image')->store('chat', 'public') : null,
        ]);

        return response()->json($message, 201);
    }

    /**
     * POST /api/chats/{chat}/read — clears unread_count shown on Secure Messages screen
     */
    public function markRead(Request $request, Chat $chat): JsonResponse
    {
        $this->authorizeParticipant($request, $chat);

        $chat->messages()
            ->whereNull('read_at')
            ->where('sender_id', '!=', $request->user()->id)
            ->update(['read_at' => now()]);

        return response()->json(['message' => 'Marked as read']);
    }

    private function authorizeParticipant(Request $request, Chat $chat): void
    {
        abort_unless(
            in_array($request->user()->id, [$chat->requester_id, $chat->recipient_id]),
            403,
            'Not a participant in this chat'
        );
    }
}
