<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: auth.php');
    exit();
}

require_once 'config/database.php';
require_once 'includes/utils.php';

$current_user_id = $_SESSION['user_id'];
$active_conversation_id = null;
$recipient_user = null;

// --- AJAX Handlers ---
// Check if the request is for an AJAX action, not a full page load.
if (isset($_POST['action']) || isset($_GET['action'])) {
    header('Content-Type: application/json');

    // Handle sending a message
    if (isset($_POST['action']) && $_POST['action'] === 'send_message') {
        $conversation_id = (int)$_POST['conversation_id'];
        $content = trim($_POST['content']);

        if (empty($content)) {
            echo json_encode(['success' => false, 'error' => 'Message cannot be empty.']);
            exit();
        }

        try {
            // Verify the current user is part of this conversation
            $verify_stmt = $pdo->prepare("SELECT COUNT(*) FROM conversation_participants WHERE conversation_id = ? AND user_id = ?");
            $verify_stmt->execute([$conversation_id, $current_user_id]);
            if ($verify_stmt->fetchColumn() == 0) {
                echo json_encode(['success' => false, 'error' => 'Not a participant of this conversation.']);
                exit();
            }

            $pdo->beginTransaction();

            $stmt = $pdo->prepare("INSERT INTO messages (conversation_id, sender_id, content) VALUES (?, ?, ?)");
            $stmt->execute([$conversation_id, $current_user_id, $content]);
            $new_message_id = $pdo->lastInsertId();

            $pdo->commit();

            // Fetch the new message to return
            $msg_stmt = $pdo->prepare("
                SELECT 
                    m.*
                FROM messages m
                WHERE m.id = ?
                GROUP BY m.id
            ");
            $msg_stmt->execute([$new_message_id]);
            $message = $msg_stmt->fetch(PDO::FETCH_ASSOC);

            // --- Notification Logic ---
            // Get recipient ID
            $recipient_stmt = $pdo->prepare("SELECT user_id FROM conversation_participants WHERE conversation_id = ? AND user_id != ?");
            $recipient_stmt->execute([$conversation_id, $current_user_id]);
            $recipient = $recipient_stmt->fetch();

            if ($recipient) {
                $recipient_id = $recipient['user_id'];
                $sender_name = $_SESSION['user_name'];
                $notification_message = htmlspecialchars($sender_name) . " sent you a message.";
                $link = "chat.php?user_id=" . $current_user_id;

                $notif_sql = "INSERT INTO notifications (user_id, sender_id, type, message, link) VALUES (?, ?, ?, ?, ?)";
                $pdo->prepare($notif_sql)->execute([$recipient_id, $current_user_id, 'new_message', $notification_message, $link]);
            }

            echo json_encode(['success' => true, 'message' => $message]);
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("Chat send message error: " . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Database error.']);
        }
        exit();
    }

    // Handle fetching new messages
    if (isset($_GET['action']) && $_GET['action'] === 'fetch_messages') {
        $conversation_id = (int)$_GET['conversation_id'];
        $last_message_id = isset($_GET['last_message_id']) ? (int)$_GET['last_message_id'] : 0;

        try {
            // Verify user is a participant
            $verify_stmt = $pdo->prepare("SELECT COUNT(*) FROM conversation_participants WHERE conversation_id = ? AND user_id = ?");
            $verify_stmt->execute([$conversation_id, $current_user_id]);
            if ($verify_stmt->fetchColumn() == 0) {
                echo json_encode(['success' => false, 'error' => 'Not a participant.']);
                exit();
            }

            $stmt = $pdo->prepare("
                SELECT 
                    m.*
                FROM messages m
                WHERE m.conversation_id = ? AND m.id > ?
                ORDER BY m.created_at ASC");
            $stmt->execute([$conversation_id, $last_message_id]);
            $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['success' => true, 'messages' => $messages]);
        } catch (PDOException $e) {
            error_log("Chat fetch messages error: " . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Database error.']);
        }
        exit();
    }

    exit();
}

// --- Page Load Logic ---

// Find or create a conversation if a user_id is provided in the URL
if (isset($_GET['user_id'])) {
    $recipient_id = (int)$_GET['user_id'];

    if ($recipient_id !== $current_user_id) {
        try {
            // Check for an existing 2-person conversation between the two users
            $stmt = $pdo->prepare("
                SELECT cp1.conversation_id
                FROM conversation_participants cp1
                JOIN conversation_participants cp2 ON cp1.conversation_id = cp2.conversation_id
                WHERE cp1.user_id = ? AND cp2.user_id = ? AND NOT EXISTS (
                    SELECT 1
                    FROM conversation_participants cp3
                    WHERE cp3.conversation_id = cp1.conversation_id
                    AND cp3.user_id NOT IN (?, ?)
                )
            ");
            $stmt->execute([$current_user_id, $recipient_id, $current_user_id, $recipient_id]);
            $conversation = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($conversation) {
                $active_conversation_id = $conversation['conversation_id'];
            } else {
                // Create a new conversation
                $pdo->beginTransaction();
                $pdo->exec("INSERT INTO conversations (created_at) VALUES (NOW())");
                $new_conversation_id = $pdo->lastInsertId();
                
                $part_stmt = $pdo->prepare("INSERT INTO conversation_participants (conversation_id, user_id) VALUES (?, ?)");
                $part_stmt->execute([$new_conversation_id, $current_user_id]);
                $part_stmt->execute([$new_conversation_id, $recipient_id]);
                
                $pdo->commit();
                $active_conversation_id = $new_conversation_id;
            }

            // Get recipient user info
            $user_stmt = $pdo->prepare("SELECT id, name, profile_picture, avatar_color, title FROM users WHERE id = ?");
            $user_stmt->execute([$recipient_id]);
            $recipient_user = $user_stmt->fetch(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = "Error starting conversation: " . $e->getMessage();
        }
    }
}

// Fetch all conversations for the current user
$conversations = [];
try {
    $stmt = $pdo->prepare("
        SELECT 
            c.id as conversation_id,
            u.id as user_id,
            u.name,
            u.profile_picture,
            u.avatar_color,
            (SELECT content FROM messages WHERE conversation_id = c.id ORDER BY created_at DESC LIMIT 1) as last_message,
            (SELECT created_at FROM messages WHERE conversation_id = c.id ORDER BY created_at DESC LIMIT 1) as last_message_time
        FROM conversations c
        JOIN conversation_participants cp ON c.id = cp.conversation_id
        JOIN users u ON cp.user_id = u.id
        WHERE cp.conversation_id IN (
            SELECT conversation_id FROM conversation_participants WHERE user_id = ?
        ) AND cp.user_id != ?
        ORDER BY last_message_time DESC
    ");
    $stmt->execute([$current_user_id, $current_user_id]);
    $conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error fetching conversations: " . $e->getMessage();
}

// If there's an active conversation, fetch its messages
$messages = [];
$last_message_date = null; // For date separators
if ($active_conversation_id) {
    try {
        $msg_stmt = $pdo->prepare("
            SELECT 
                m.*
            FROM messages m
            WHERE m.conversation_id = ?
            ORDER BY m.created_at ASC");
        $msg_stmt->execute([$active_conversation_id]);
        $messages = $msg_stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $error = "Error fetching messages: " . $e->getMessage();
    }
}

include(__DIR__ . '/Components/header.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat - DevBug</title>
    <link rel="stylesheet" href="/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Fira+Code:wght@400;500;600&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            background: var(--bg-primary);
            overflow: hidden; /* Prevent scrolling of the main page */
        }
        .chat-container {
            display: grid;
            grid-template-columns: 350px 1fr;
            height: calc(100vh - 85px); /* Adjust based on header height */
            background: var(--bg-secondary);
        }

        /* Sidebar */
        .chat-sidebar {
            background: var(--bg-primary);
            border-right: 1px solid var(--border-color, var(--border));
            display: flex;
            flex-direction: column;
            overflow: hidden; /* Let child elements handle scrolling */
        }

        .sidebar-header {
            padding: 20px;
            border-bottom: 1px solid var(--border-color, var(--border));
        }

        .sidebar-header h2 {
            font-size: 1.5rem;
            margin-bottom: 15px;
        }

        .search-chat input {
            width: 100%;
            padding: 10px 15px;
            border-radius: 8px;
            border: 1px solid var(--border);
            background: var(--bg-secondary);
            color: var(--text-primary);
            font-size: 0.95rem;
        }

        .conversation-list {
            flex: 1;
            overflow-y: auto; /* This will now scroll independently */
        }

        .conversation-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px 20px;
            cursor: pointer;
            transition: background-color 0.2s ease, border-left-color 0.2s ease;
            border-bottom: 1px solid var(--border);
            text-decoration: none;
            border-left: 4px solid transparent;
        }

        .conversation-item:hover {
            background: var(--bg-secondary);
        }

        .conversation-item.active {
            background: var(--accent-primary);
            border-left-color: var(--accent-secondary);
        }

        .conversation-item.active .conversation-details h3,
        .conversation-item.active .conversation-details p,
        .conversation-item.active .conversation-time {
            color: white;
        }

        .conversation-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 1.2rem;
            flex-shrink: 0;
            overflow: hidden;
        }
        .conversation-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .conversation-details {
            flex: 1;
            overflow: hidden;
        }

        .conversation-details h3 {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 4px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .conversation-details p {
            font-size: 0.9rem;
            color: var(--text-secondary);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .conversation-time {
            font-size: 0.8rem;
            color: var(--text-muted);
            align-self: flex-start;
        }

        /* Main Chat Window */
        .chat-window {
            display: flex;
            flex-direction: column;
            overflow: hidden; /* Let child elements handle scrolling */
        }

        .chat-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            padding: 15px 25px;
            background: var(--bg-card);
            border-bottom: 1px solid var(--border);
            flex-shrink: 0;
        }
        .chat-header-user {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .chat-header h3 {
            font-size: 1.2rem;
        }

        .messages-container {
            flex: 1;
            padding: 25px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
        }

        /* Custom Scrollbar for a more refined look */
        .conversation-list::-webkit-scrollbar,
        .messages-container::-webkit-scrollbar {
            width: 8px;
        }

        .conversation-list::-webkit-scrollbar-track,
        .messages-container::-webkit-scrollbar-track {
            background: transparent;
        }

        .conversation-list::-webkit-scrollbar-thumb,
        .messages-container::-webkit-scrollbar-thumb {
            background: var(--border);
            border-radius: 4px;
        }

        .date-separator {
            align-self: center;
            background: var(--bg-card);
            color: var(--text-muted);
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.8rem;
            margin: 15px 0;
        }

        .message-wrapper {
            display: flex;
            flex-direction: column;
            animation: message-fade-in 0.3s ease-out;
        }

        .message {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            max-width: 75%;
        }
        
        .message-body {
            display: flex;
            flex-direction: column;
        }

        .message-content {
            background: var(--bg-card);
            padding: 12px 18px;
            border-radius: 18px;
            color: var(--text-secondary);
            line-height: 1.5;
            position: relative;
        }

        .message.sent {
            align-self: flex-end;
            flex-direction: row-reverse;
        }

        .message.sent .message-content {
            background: var(--accent-primary);
            color: white;
            border-bottom-right-radius: 4px;
        }

        .message.received .message-content {
            background: var(--bg-card);
            color: var(--text-primary);
            border-bottom-left-radius: 4px;
        }
        
        /* Message bubble tails */
        .message.received .message-content::before {
            content: '';
            position: absolute;
            bottom: 0;
            left: -8px;
            width: 0;
            height: 0;
            border-style: solid;
            border-width: 0 10px 10px 0;
            border-color: transparent var(--bg-card) transparent transparent;
        }

        .message-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            flex-shrink: 0;
            overflow: hidden;
            align-self: flex-end;
        }
        .message-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .message-time {
            font-size: 0.75rem;
            color: var(--text-muted);
            margin-top: 5px;
            padding: 0 5px;
        }

        .message.sent .message-time {
            text-align: right;
        }

        .chat-input-area {
            padding: 20px 25px;
            background: var(--bg-card);
            border-top: 1px solid var(--border);
            flex-shrink: 0;
        }

        .chat-input-area form {
            display: flex;
            gap: 15px;
        }

        .chat-input-area input {
            flex: 1;
            padding: 14px 20px;
            border-radius: 8px;
            border: 1px solid var(--border);
            background: var(--bg-secondary);
            color: var(--text-primary);
            font-size: 1rem;
        }

        .chat-input-area button {
            padding: 0 25px;
            font-size: 1.2rem;
        }

        .btn-icon {
            background: transparent;
            border: none;
            color: var(--text-muted);
            font-size: 1.3rem;
            padding: 0 15px;
            cursor: pointer;
        }

        .message-image-attachment {
            width: 100%;
            height: 120px;
            object-fit: cover; /* This can be changed to `contain` if you prefer to see the whole image */
            border-radius: 8px;
            cursor: zoom-in;
            transition: var(--transition);
        }
        .message-image-attachment:hover {
        }

        /* Image Modal Styles from solution-details.php */
        .image-modal {
            display: none;
            position: fixed;
            top: 0; left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.95);
            z-index: 1000;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        .modal-content { max-width: 95%; max-height: 95%; display: flex; flex-direction: column; align-items: center; }
        .modal-image { max-width: 100%; max-height: calc(100vh - 100px); border-radius: 8px; object-fit: contain; }
        .modal-controls { display: flex; gap: 15px; margin-top: 20px; align-items: center; }
        .close-modal { background: var(--danger); color: white; border: none; border-radius: 8px; padding: 10px 20px; cursor: pointer; font-weight: 600; transition: var(--transition); }
        .close-modal:hover { background: #dc2626; transform: translateY(-2px); }
        .image-counter { color: var(--text-primary); font-size: 0.9rem; }

        @media (max-width: 968px) {
            .chat-container {
                grid-template-columns: 1fr;
            }
            .chat-sidebar {
                display: <?php echo $active_conversation_id ? 'none' : 'flex'; ?>;
            }
            .chat-window {
                display: <?php echo $active_conversation_id ? 'flex' : 'none'; ?>;
            }
            .chat-header {
                padding: 15px;
            }
            .back-to-conversations {
                display: block;
                margin-right: 15px;
                font-size: 1.2rem;
                color: var(--text-primary);
            }
        }
    </style>
</head>
<body>

<div class="chat-container">
    <!-- Sidebar with conversation list -->
    <aside class="chat-sidebar">
        <div class="sidebar-header">
            <h2>Messages</h2>
            <div class="search-chat">
                <input type="text" id="conversation-search" placeholder="Search conversations...">
            </div>
        </div>
        <div class="conversation-list">
            <?php if (!empty($conversations)): ?>
                <?php foreach ($conversations as $convo): ?>
                    <a href="chat.php?user_id=<?php echo $convo['user_id']; ?>" class="conversation-item <?php echo ($active_conversation_id == $convo['conversation_id']) ? 'active' : ''; ?>">
                        <div class="conversation-avatar" style="background-color: <?php echo htmlspecialchars($convo['avatar_color']); ?>">
                            <?php if (!empty($convo['profile_picture'])): ?>
                                <img src="<?php echo htmlspecialchars($convo['profile_picture']); ?>" alt="<?php echo htmlspecialchars($convo['name']); ?>">
                            <?php else: ?>
                                <?php echo strtoupper(substr($convo['name'], 0, 2)); ?>
                            <?php endif; ?>
                        </div>
                        <div class="conversation-details">
                            <h3><?php echo htmlspecialchars($convo['name']); ?></h3>
                            <p><?php echo htmlspecialchars(substr($convo['last_message'] ?? 'No messages yet', 0, 30)); ?>...</p>
                        </div>
                        <span class="conversation-time"><?php echo $convo['last_message_time'] ? timeAgo($convo['last_message_time']) : ''; ?></span>
                    </a>
                <?php endforeach; ?>
            <?php else: ?>
                <p style="text-align: center; color: var(--text-muted); padding: 20px;">No conversations yet.</p>
            <?php endif; ?>
        </div>
    </aside>

    <!-- Main chat window -->
    <main class="chat-window">
        <?php if ($active_conversation_id && $recipient_user): ?> 
            <div class="chat-header">
                <div class="chat-header-user">
                    <a href="chat.php" class="back-to-conversations" style="display: none;"><i class="fas fa-arrow-left"></i></a>
                    <a href="profile.php?id=<?php echo $recipient_user['id']; ?>" class="conversation-avatar" style="background-color: <?php echo htmlspecialchars($recipient_user['avatar_color']); ?>">
                        <?php if (!empty($recipient_user['profile_picture'])): ?>
                            <img src="<?php echo htmlspecialchars($recipient_user['profile_picture']); ?>" alt="<?php echo htmlspecialchars($recipient_user['name']); ?>">
                        <?php else: ?>
                            <?php echo strtoupper(substr($recipient_user['name'], 0, 2)); ?>
                        <?php endif; ?>
                    </a>
                    <div>
                        <a href="profile.php?id=<?php echo $recipient_user['id']; ?>" style="text-decoration: none; color: var(--text-primary);"><h3><?php echo htmlspecialchars($recipient_user['name']); ?></h3></a>
                        <p style="color: var(--accent-tertiary); font-size: 0.9rem;"><?php echo htmlspecialchars($recipient_user['title'] ?? 'Developer'); ?></p>
                    </div>
                </div>
            </div>
 
            <div class="messages-container" id="messages-container">
                <?php foreach ($messages as $message): ?>
                    <?php
                    $current_message_date = date('Y-m-d', strtotime($message['created_at']));
                    $attachments_html = ''; // Initialize variable
                    if ($current_message_date !== $last_message_date) {
                        echo '<div class="date-separator">' . date('F j, Y', strtotime($current_message_date)) . '</div>';
                        $last_message_date = $current_message_date;
                    }
                    ?>
                    <div class="message <?php echo $message['sender_id'] == $current_user_id ? 'sent' : 'received'; ?>">
                        <?php if ($message['sender_id'] != $current_user_id): ?>
                            <div class="message-avatar">
                                <?php if (!empty($recipient_user['profile_picture'])): ?>
                                    <img src="<?php echo htmlspecialchars($recipient_user['profile_picture']); ?>" alt="<?php echo htmlspecialchars($recipient_user['name']); ?>">
                                <?php else: ?>
                                    <div style="width:100%; height:100%; background-color: <?php echo htmlspecialchars($recipient_user['avatar_color']); ?>; display:flex; align-items:center; justify-content:center; color:white; font-weight:bold;">
                                        <?php echo strtoupper(substr($recipient_user['name'], 0, 2)); ?>
                                    </div>
                                <?php endif; ?>
                        <?php endif; ?>
                        <div class="message-body">
                            <div class="message-content">
                                <?php if (isset($message['content']) && trim($message['content']) !== ''): ?>
                                    <div class="message-text"><?php echo nl2br(htmlspecialchars($message['content'])); ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="message-time"><?php echo timeAgo($message['created_at']); ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="chat-input-area">
                <form id="chat-form">
                    <input type="text" id="message-input" placeholder="Type a message..." autocomplete="off">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i></button>
                </form>
            </div>
        <?php else: ?>
            <div class="no-chat-selected">
                <i class="fas fa-comments"></i> 
                <h3>Select a conversation</h3>
                <p>Choose a conversation from the list to start chatting.</p>
            </div>
        <?php endif; ?>
    </main>
</div>

<!-- Image Modal -->
<div id="imageModal" class="image-modal">
    <div class="modal-content">
        <img id="modalImage" src="" alt="" class="modal-image">
        <div class="modal-controls">
            <span class="image-counter" id="imageCounter"></span>
            <button id="closeModal" class="close-modal">Close</button>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const messagesContainer = document.getElementById('messages-container');
    const chatForm = document.getElementById('chat-form');
    const messageInput = document.getElementById('message-input');
    const conversationId = <?php echo json_encode($active_conversation_id); ?>;
    const conversationSearch = document.getElementById('conversation-search');
    const currentUserId = <?php echo json_encode($current_user_id); ?>;

    // --- Image Modal Logic ---
    let currentImageIndex = 0;
    let currentMessageImages = [];
    const imageModal = document.getElementById('imageModal');
    const modalImage = document.getElementById('modalImage');
    const imageCounter = document.getElementById('imageCounter');
    const closeModalBtn = document.getElementById('closeModal');

    window.openImageModal = function(clickedImageSrc, allImagesInMessage) {
        currentMessageImages = allImagesInMessage;
        currentImageIndex = currentMessageImages.indexOf(clickedImageSrc);
        
        modalImage.src = clickedImageSrc;
        imageCounter.textContent = `Image ${currentImageIndex + 1} of ${currentMessageImages.length}`;
        imageModal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }

    function closeImageModal() {
        imageModal.style.display = 'none';
        document.body.style.overflow = 'auto';
    }

    closeModalBtn.addEventListener('click', closeImageModal);
    imageModal.addEventListener('click', (e) => { if (e.target === imageModal) closeImageModal(); });

    document.addEventListener('keydown', function(e) {
        if (imageModal.style.display === 'flex') {
            if (e.key === 'Escape') closeImageModal();
            if (e.key === 'ArrowLeft' && currentImageIndex > 0) {
                openImageModal(currentMessageImages[currentImageIndex - 1], currentMessageImages);
            } else if (e.key === 'ArrowRight' && currentImageIndex < currentMessageImages.length - 1) {
                openImageModal(currentMessageImages[currentImageIndex + 1], currentMessageImages);
            }
        }
    });
    let lastMessageId = <?php echo !empty($messages) ? end($messages)['id'] : 0; ?>;

    function scrollToBottom() {
        if (messagesContainer) {
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }
    }

    scrollToBottom();

    function renderMessage(message, isNew = false) {
        // Check for date separator
        const lastMessageElement = messagesContainer.querySelector('.message-wrapper:last-child');
        let lastMessageDate = null;
        if (lastMessageElement) {
            const lastTimestamp = lastMessageElement.dataset.timestamp;
            lastMessageDate = new Date(lastTimestamp).toISOString().split('T')[0];
        }

        const currentMessageDate = new Date(message.created_at + ' UTC').toISOString().split('T')[0];

        if (!lastMessageDate || currentMessageDate > lastMessageDate) {
            const dateSeparator = document.createElement('div');
            dateSeparator.className = 'date-separator';
            dateSeparator.textContent = new Date(currentMessageDate).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
            messagesContainer.appendChild(dateSeparator);
        }

        const messageDiv = document.createElement('div');
        const isSent = message.sender_id == currentUserId;
        messageDiv.className = `message-wrapper message ${isSent ? 'sent' : 'received'}`;
        messageDiv.dataset.timestamp = message.created_at + ' UTC';

        let attachmentsHtml = '';
        if (message.attachment_paths) {
            const paths = message.attachment_paths.split('||');
            const names = message.attachment_names.split('||');
            const types = message.attachment_types.split('||');

            const imageAttachments = paths.filter((path, i) => types[i] && types[i].startsWith('image/'));

            attachmentsHtml += '<div class="message-attachments">';
            paths.forEach((path, i) => {
                if (types[i] && types[i].startsWith('image/')) {
                    attachmentsHtml += `<img src="${path}" class="message-image-attachment" onclick='openImageModal(${JSON.stringify(path)}, ${JSON.stringify(imageAttachments)})'>`;
                } else {
                    attachmentsHtml += `<a href="${path}" class="message-file-attachment" download="${names[i]}"><i class="fas fa-file-alt"></i> ${names[i]}</a>`;
                }
            });
            attachmentsHtml += '</div>';
        }

        let messageTextHtml = ''; 
        if (message.content && message.content.trim() !== '') {
            messageTextHtml = `<div class="message-text">${message.content.replace(/\n/g, '<br>')}</div>`;
        }

        messageDiv.innerHTML = `
            <div class="message-body"> 
                <div class="message-content">
                    ${messageTextHtml}
                </div>
                <div class="message-time">${timeAgo(message.created_at)}</div>
            </div>
        `;
        messagesContainer.appendChild(messageDiv);

        // Only auto-scroll for new messages sent by the user or if they are already at the bottom
        if (isNew) {
            scrollToBottom();
        }
    }

    // Handle form submission
    if (chatForm) {
        chatForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const content = messageInput.value.trim();
            if (!content) return;

            const formData = new FormData();
            formData.append('action', 'send_message');
            formData.append('conversation_id', conversationId);
            formData.append('content', content);

            fetch('chat.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    renderMessage(data.message, true);
                    lastMessageId = data.message.id;
                } else {
                    alert('Error: ' + data.error);
                    messageInput.value = content; // Restore message on failure
                }
            })
            .catch(error => {
                console.error('Error sending message:', error);
                messageInput.value = content; // Restore message on failure
            });
        });
    }

    // On page load, render previews for any attachments stored in the session
    if (sessionAttachments.length > 0) {
        sessionAttachments.forEach(fileInfo => createAttachmentPreview(fileInfo));
    }

    // Fetch new messages periodically
    function fetchNewMessages() {
        if (!conversationId) return;

        fetch(`chat.php?action=fetch_messages&conversation_id=${conversationId}&last_message_id=${lastMessageId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.messages.length > 0) {
                data.messages.forEach(message => {
                    renderMessage(message, true);
                });
                lastMessageId = data.messages[data.messages.length - 1].id;
            }
        })
        .catch(error => console.error('Error fetching messages:', error));
    }

    if (conversationId) {
        setInterval(fetchNewMessages, 3000); // Poll every 3 seconds
    }

    // Conversation search
    if (conversationSearch) {
        conversationSearch.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const conversations = document.querySelectorAll('.conversation-item');

            conversations.forEach(convo => {
                const name = convo.querySelector('.conversation-details h3').textContent.toLowerCase();
                const lastMessage = convo.querySelector('.conversation-details p').textContent.toLowerCase();
                if (name.includes(searchTerm) || lastMessage.includes(searchTerm)) {
                    convo.style.display = 'flex';
                } else {
                    convo.style.display = 'none';
                }
            });
        });
    }
    // Utility to calculate time ago
    function timeAgo(dateString) {
        const date = new Date(dateString + ' UTC');
        const now = new Date();
        const seconds = Math.floor((now - date) / 1000);

        let interval = seconds / 31536000;
        if (interval > 1) return Math.floor(interval) + " years ago";
        interval = seconds / 2592000;
        if (interval > 1) return Math.floor(interval) + " months ago";
        interval = seconds / 86400;
        if (interval > 1) return Math.floor(interval) + " days ago";
        interval = seconds / 3600;
        if (interval > 1) return Math.floor(interval) + " hours ago";
        interval = seconds / 60;
        if (interval > 1) return Math.floor(interval) + " minutes ago";
        return "Just now";
    }
});
</script>
</body>
</html>