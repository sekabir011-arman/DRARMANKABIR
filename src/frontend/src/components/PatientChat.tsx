import { Button } from "@/components/ui/button";
import { ScrollArea } from "@/components/ui/scroll-area";
import { format, formatDistanceToNow } from "date-fns";
import { CheckCheck, Eye, MessageCircle, Send, X } from "lucide-react";
import { useEffect, useRef, useState } from "react";

export interface ChatMessage {
  id: string;
  senderId: string;
  senderRole: "doctor" | "admin" | "patient" | "staff";
  senderName: string;
  text: string;
  timestamp: string;
  /** Filled when a doctor/admin views the message */
  seenAt?: string;
  seenBy?: string;
}

interface PatientChatProps {
  patientId: bigint;
  currentRole: string;
  currentUserName: string;
  /** If true, renders as a floating panel overlay */
  floating?: boolean;
  onClose?: () => void;
}

function getChatKey(patientId: bigint) {
  return `patientChat_${patientId}`;
}

function loadMessages(patientId: bigint): ChatMessage[] {
  try {
    const raw = localStorage.getItem(getChatKey(patientId));
    return raw ? JSON.parse(raw) : [];
  } catch {
    return [];
  }
}

function saveMessages(patientId: bigint, msgs: ChatMessage[]) {
  localStorage.setItem(getChatKey(patientId), JSON.stringify(msgs));
}

/** Mark all unread patient messages as seen by the doctor/admin */
function markPatientMessagesSeen(
  patientId: bigint,
  seenBy: string,
): ChatMessage[] {
  const msgs = loadMessages(patientId);
  const now = new Date().toISOString();
  let changed = false;
  const updated = msgs.map((m) => {
    if (m.senderRole === "patient" && !m.seenAt) {
      changed = true;
      return { ...m, seenAt: now, seenBy };
    }
    return m;
  });
  if (changed) saveMessages(patientId, updated);
  return updated;
}

function seenAgo(seenAt: string): string {
  try {
    return formatDistanceToNow(new Date(seenAt), { addSuffix: true });
  } catch {
    return "Seen";
  }
}

export default function PatientChat({
  patientId,
  currentRole,
  currentUserName,
  floating = false,
  onClose,
}: PatientChatProps) {
  const isDoctor = currentRole === "doctor" || currentRole === "admin";

  const [messages, setMessages] = useState<ChatMessage[]>(() =>
    isDoctor
      ? markPatientMessagesSeen(patientId, currentUserName)
      : loadMessages(patientId),
  );
  const [text, setText] = useState("");
  const bottomRef = useRef<HTMLDivElement>(null);

  // When doctor opens chat, mark patient messages as seen
  // biome-ignore lint/correctness/useExhaustiveDependencies: run when doctor opens chat for this patient
  useEffect(() => {
    if (isDoctor) {
      const updated = markPatientMessagesSeen(patientId, currentUserName);
      setMessages(updated);
    }
  }, [patientId, isDoctor]);

  // Patient side: poll every 5s to pick up "seenAt" updates written by the doctor
  useEffect(() => {
    if (isDoctor) return;
    const iv = setInterval(() => {
      setMessages(loadMessages(patientId));
    }, 5000);
    return () => clearInterval(iv);
  }, [patientId, isDoctor]);

  // biome-ignore lint/correctness/useExhaustiveDependencies: intentional scroll on message change
  useEffect(() => {
    bottomRef.current?.scrollIntoView({ behavior: "smooth" });
  }, [messages]);

  function sendMessage() {
    const trimmed = text.trim();
    if (!trimmed) return;
    const msg: ChatMessage = {
      id: `${Date.now()}_${Math.random().toString(36).slice(2)}`,
      senderId: currentUserName,
      senderRole: currentRole as ChatMessage["senderRole"],
      senderName: currentUserName,
      text: trimmed,
      timestamp: new Date().toISOString(),
    };
    const updated = [...messages, msg];
    setMessages(updated);
    saveMessages(patientId, updated);
    setText("");
  }

  const inner = (
    <div className="flex flex-col h-full">
      {/* Header */}
      <div className="flex items-center justify-between px-4 py-3 border-b border-gray-100 bg-gradient-to-r from-teal-600 to-teal-700 rounded-t-xl">
        <div className="flex items-center gap-2">
          <MessageCircle className="w-4 h-4 text-white" />
          <span className="text-sm font-semibold text-white">Patient Chat</span>
        </div>
        {onClose && (
          <button
            type="button"
            onClick={onClose}
            className="text-white/80 hover:text-white transition-colors"
            data-ocid="patient_chat.close_button"
          >
            <X className="w-4 h-4" />
          </button>
        )}
      </div>

      {/* Messages */}
      <ScrollArea className="flex-1 px-4 py-3">
        {messages.length === 0 ? (
          <div
            className="text-center py-8"
            data-ocid="patient_chat.empty_state"
          >
            <MessageCircle className="w-8 h-8 text-gray-300 mx-auto mb-2" />
            <p className="text-sm text-gray-400">No messages yet</p>
            <p className="text-xs text-gray-300 mt-1">Start a conversation</p>
          </div>
        ) : (
          <div className="space-y-3">
            {messages.map((msg) => {
              const isOwn = isDoctor
                ? msg.senderRole === "doctor" || msg.senderRole === "admin"
                : msg.senderRole === "patient";
              // Patient sees "Seen" indicator on their own messages
              const showSeen =
                !isDoctor && msg.senderRole === "patient" && msg.seenAt;
              return (
                <div
                  key={msg.id}
                  className={`flex flex-col ${isOwn ? "items-end" : "items-start"}`}
                  data-ocid="patient_chat.row"
                >
                  <div
                    className={`max-w-[75%] rounded-2xl px-3 py-2 ${
                      isOwn
                        ? "bg-teal-600 text-white rounded-br-sm"
                        : "bg-gray-100 text-gray-800 rounded-bl-sm"
                    }`}
                  >
                    {!isOwn && (
                      <p className="text-[10px] font-semibold mb-0.5 opacity-70">
                        {msg.senderName}
                      </p>
                    )}
                    <p className="text-sm leading-relaxed">{msg.text}</p>
                    <p
                      className={`text-[10px] mt-0.5 ${
                        isOwn ? "text-teal-200" : "text-gray-400"
                      }`}
                    >
                      {format(new Date(msg.timestamp), "h:mm a")}
                    </p>
                  </div>
                  {/* Seen receipt — only shown to patient on their own messages */}
                  {showSeen && (
                    <div
                      className="flex items-center gap-1 mt-0.5 px-1"
                      data-ocid="patient_chat.seen_indicator"
                    >
                      <CheckCheck className="w-3 h-3 text-teal-500" />
                      <Eye className="w-3 h-3 text-teal-500" />
                      <span className="text-[10px] text-teal-600 font-medium">
                        Seen {seenAgo(msg.seenAt!)}
                      </span>
                    </div>
                  )}
                </div>
              );
            })}
            <div ref={bottomRef} />
          </div>
        )}
      </ScrollArea>

      {/* Input */}
      <div className="px-3 py-3 border-t border-gray-100 flex gap-2">
        <input
          className="flex-1 border border-gray-200 rounded-full px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-teal-300 bg-gray-50"
          placeholder="Type a message..."
          value={text}
          onChange={(e) => setText(e.target.value)}
          onKeyDown={(e) => e.key === "Enter" && !e.shiftKey && sendMessage()}
          data-ocid="patient_chat.input"
        />
        <Button
          size="sm"
          onClick={sendMessage}
          disabled={!text.trim()}
          className="bg-teal-600 hover:bg-teal-700 text-white rounded-full w-9 h-9 p-0"
          data-ocid="patient_chat.submit_button"
        >
          <Send className="w-3.5 h-3.5" />
        </Button>
      </div>
    </div>
  );

  if (floating) {
    return (
      <div
        className="fixed bottom-6 right-6 w-80 h-[420px] bg-white rounded-xl shadow-2xl border border-gray-200 flex flex-col z-50 overflow-hidden"
        data-ocid="patient_chat.modal"
      >
        {inner}
      </div>
    );
  }

  return (
    <div
      className="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden flex flex-col"
      style={{ minHeight: 380 }}
      data-ocid="patient_chat.panel"
    >
      {inner}
    </div>
  );
}
