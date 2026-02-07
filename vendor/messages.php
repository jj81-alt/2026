<?php
// vendor/messages.php
require_once '../includes/session.php';
require_once '../config/database.php';
requireVendor();

$database = new Database();
$db = $database->getConnection();

// Get vendor profile
$stmt = $db->prepare("SELECT vp.* FROM vendor_profiles vp WHERE vp.user_id = ?");
$stmt->execute([getUserId()]);
$vendor = $stmt->fetch(PDO::FETCH_ASSOC);
$vendor_id = $vendor['vendor_id'];

$message = '';
$error = '';

// Handle sending message
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'send') {
        $conversation_id = intval($_POST['conversation_id'] ?? 0);
        $message_text = trim($_POST['message'] ?? '');
        
        if (!empty($message_text) && $conversation_id > 0) {
            $stmt = $db->prepare("INSERT INTO messages (conversation_id, sender_id, message_text, created_at) VALUES (?, ?, ?, CURRENT_TIMESTAMP)");
            if ($stmt->execute([$conversation_id, getUserId(), $message_text])) {
                // Update conversation last message time
                $stmt = $db->prepare("UPDATE conversations SET updated_at = CURRENT_TIMESTAMP WHERE conversation_id = ?");
                $stmt->execute([$conversation_id]);
                $message = 'Message sent successfully!';
            } else {
                $error = 'Failed to send message';
            }
        }
    }
}

// Get selected conversation
$selected_conversation = intval($_GET['conv'] ?? 0);

// Get all conversations for this vendor
$stmt = $db->prepare("
    SELECT 
        c.*,
        u.full_name as customer_name,
        u.phone_number as customer_phone,
        cp.customer_id,
        (SELECT message_text FROM messages WHERE conversation_id = c.conversation_id ORDER BY created_at DESC LIMIT 1) as last_message,
        (SELECT created_at FROM messages WHERE conversation_id = c.conversation_id ORDER BY created_at DESC LIMIT 1) as last_message_time,
        (SELECT COUNT(*) FROM messages WHERE conversation_id = c.conversation_id AND sender_id != ? AND is_read = FALSE) as unread_count
    FROM conversations c
    JOIN customer_profiles cp ON c.customer_id = cp.customer_id
    JOIN users u ON cp.user_id = u.user_id
    WHERE c.vendor_id = ?
    ORDER BY c.updated_at DESC
");
$stmt->execute([getUserId(), $vendor_id]);
$conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get messages for selected conversation
$messages_list = [];
$current_conversation = null;
if ($selected_conversation > 0) {
    // Get conversation details
    foreach ($conversations as $conv) {
        if ($conv['conversation_id'] == $selected_conversation) {
            $current_conversation = $conv;
            break;
        }
    }
    
    if ($current_conversation) {
        // Get messages
        $stmt = $db->prepare("
            SELECT 
                m.*,
                u.full_name as sender_name,
                u.user_id
            FROM messages m
            JOIN users u ON m.sender_id = u.user_id
            WHERE m.conversation_id = ?
            ORDER BY m.created_at ASC
        ");
        $stmt->execute([$selected_conversation]);
        $messages_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Mark messages as read
        $stmt = $db->prepare("UPDATE messages SET is_read = TRUE WHERE conversation_id = ? AND sender_id != ?");
        $stmt->execute([$selected_conversation, getUserId()]);
    }
}

// Calculate total unread messages
$total_unread = 0;
foreach ($conversations as $conv) {
    $total_unread += $conv['unread_count'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - MarketConnect</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .messages-container {
            height: calc(100vh - 350px);
            min-height: 400px;
        }
        .conversation-list {
            height: calc(100vh - 280px);
            min-height: 500px;
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Navigation -->
    <nav class="bg-white shadow-lg">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center h-16">
            <div class="flex items-center">
                <h1 class="text-xl md:text-2xl font-bold text-green-600">🏪 MarketConnect</h1>
                <span class="ml-2 md:ml-4 px-2 md:px-3 py-1 bg-blue-100 text-blue-800 text-xs font-semibold rounded-full">VENDOR</span>
            </div>
            
            <!-- Mobile Menu Button -->
            <button id="mobile-menu-button" class="md:hidden text-gray-700">
                <i class="fas fa-bars text-2xl"></i>
            </button>
            
            <!-- Desktop Menu -->
            <div class="hidden md:flex items-center space-x-4 lg:space-x-6">
                <a href="products.php" class="text-gray-700 hover:text-green-600">
                    <i class="fas fa-box"></i><span class="hidden lg:inline"> Products</span>
                </a>
                <a href="orders.php" class="text-gray-700 hover:text-green-600">
                    <i class="fas fa-shopping-cart"></i><span class="hidden lg:inline"> Orders</span>
                </a>
                <a href="messages.php" class="text-gray-700 hover:text-green-600">
                    <i class="fas fa-comments"></i><span class="hidden lg:inline"> Messages</span>
                </a>
                <a href="../index.php" class="text-green-600 hover:text-green-800 font-semibold">
                    <i class="fas fa-store-alt"></i><span class="hidden lg:inline"> Marketplace</span>
                </a>
                <span class="text-gray-700 hidden lg:inline"><?php echo htmlspecialchars($vendor['business_name']); ?></span>
                <a href="../logout.php" class="text-red-600 hover:text-red-800">
                    <i class="fas fa-sign-out-alt"></i><span class="hidden lg:inline"> Logout</span>
                </a>
            </div>
        </div>
        
        <!-- Mobile Menu -->
        <div id="mobile-menu" class="mobile-menu md:hidden">
            <a href="products.php" class="block py-2 text-gray-700 hover:bg-gray-50">
                <i class="fas fa-box mr-2"></i> Products
            </a>
            <a href="orders.php" class="block py-2 text-gray-700 hover:bg-gray-50">
                <i class="fas fa-shopping-cart mr-2"></i> Orders
            </a>
            <a href="messages.php" class="block py-2 text-gray-700 hover:bg-gray-50">
                <i class="fas fa-comments mr-2"></i> Messages
            </a>
            <a href="../index.php" class="block py-2 text-green-600 hover:bg-green-50">
                <i class="fas fa-store-alt mr-2"></i> Browse Marketplace
            </a>
            <div class="py-2 px-4 bg-gray-100 text-gray-700 text-sm">
                <?php echo htmlspecialchars($vendor['business_name']); ?>
            </div>
            <a href="../logout.php" class="block py-2 text-red-600 hover:bg-red-50">
                <i class="fas fa-sign-out-alt mr-2"></i> Logout
            </a>
        </div>
    </div>
</nav>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="mb-6">
            <h1 class="text-3xl font-bold text-gray-800">Messages</h1>
            <p class="text-gray-600 mt-1">Communicate with your customers</p>
        </div>

        <?php if ($message): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 gap-4 md:gap-6 mb-8 stats-grid">
            <!-- Conversations List -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-lg shadow">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h2 class="text-lg font-semibold text-gray-800">
                            Conversations
                            <?php if ($total_unread > 0): ?>
                                <span class="ml-2 bg-red-500 text-white text-xs px-2 py-1 rounded-full"><?php echo $total_unread; ?></span>
                            <?php endif; ?>
                        </h2>
                    </div>
                    <div class="conversation-list overflow-y-auto">
                        <?php if (empty($conversations)): ?>
                            <div class="p-6 text-center text-gray-500">
                                <i class="fas fa-comments text-4xl mb-3 text-gray-300"></i>
                                <p>No conversations yet</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($conversations as $conv): ?>
                                <a href="?conv=<?php echo $conv['conversation_id']; ?>" 
                                   class="block p-4 border-b hover:bg-gray-50 transition <?php echo $selected_conversation == $conv['conversation_id'] ? 'bg-green-50 border-l-4 border-green-500' : ''; ?>">
                                    <div class="flex items-start justify-between">
                                        <div class="flex-1">
                                            <div class="flex items-center">
                                                <div class="w-10 h-10 bg-green-500 rounded-full flex items-center justify-center text-white font-semibold mr-3">
                                                    <?php echo strtoupper(substr($conv['customer_name'], 0, 1)); ?>
                                                </div>
                                                <div class="flex-1">
                                                    <h3 class="font-semibold text-gray-800"><?php echo htmlspecialchars($conv['customer_name']); ?></h3>
                                                    <p class="text-xs text-gray-500">
                                                        <i class="fas fa-phone"></i> <?php echo htmlspecialchars($conv['customer_phone']); ?>
                                                    </p>
                                                </div>
                                            </div>
                                            <?php if ($conv['last_message']): ?>
                                                <p class="text-sm text-gray-600 mt-2 truncate">
                                                    <?php echo htmlspecialchars(substr($conv['last_message'], 0, 50)); ?>...
                                                </p>
                                                <p class="text-xs text-gray-400 mt-1">
                                                    <?php 
                                                    $time = strtotime($conv['last_message_time']);
                                                    $diff = time() - $time;
                                                    if ($diff < 60) {
                                                        echo 'Just now';
                                                    } elseif ($diff < 3600) {
                                                        echo floor($diff / 60) . ' min ago';
                                                    } elseif ($diff < 86400) {
                                                        echo floor($diff / 3600) . ' hours ago';
                                                    } else {
                                                        echo date('M d, Y', $time);
                                                    }
                                                    ?>
                                                </p>
                                            <?php endif; ?>
                                        </div>
                                        <?php if ($conv['unread_count'] > 0): ?>
                                            <span class="bg-red-500 text-white text-xs px-2 py-1 rounded-full ml-2">
                                                <?php echo $conv['unread_count']; ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Messages Area -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-lg shadow h-full flex flex-col">
                    <?php if ($current_conversation): ?>
                        <!-- Conversation Header -->
                        <div class="px-6 py-4 border-b border-gray-200 bg-green-50">
                            <div class="flex items-center">
                                <div class="w-12 h-12 bg-green-500 rounded-full flex items-center justify-center text-white font-bold text-lg mr-4">
                                    <?php echo strtoupper(substr($current_conversation['customer_name'], 0, 1)); ?>
                                </div>
                                <div>
                                    <h2 class="text-lg font-semibold text-gray-800"><?php echo htmlspecialchars($current_conversation['customer_name']); ?></h2>
                                    <p class="text-sm text-gray-600">
                                        <i class="fas fa-phone"></i> <?php echo htmlspecialchars($current_conversation['customer_phone']); ?>
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Messages List -->
                        <div class="messages-container overflow-y-auto p-6 space-y-4 flex-1">
                            <?php if (empty($messages_list)): ?>
                                <div class="text-center text-gray-500 py-12">
                                    <i class="fas fa-comment-slash text-4xl mb-3 text-gray-300"></i>
                                    <p>No messages yet. Start the conversation!</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($messages_list as $msg): ?>
                                    <?php $is_vendor = ($msg['user_id'] == getUserId()); ?>
                                    <div class="flex <?php echo $is_vendor ? 'justify-end' : 'justify-start'; ?>">
                                        <div class="max-w-md">
                                            <?php if (!$is_vendor): ?>
                                                <p class="text-xs text-gray-500 mb-1"><?php echo htmlspecialchars($msg['sender_name']); ?></p>
                                            <?php endif; ?>
                                            <div class="<?php echo $is_vendor ? 'bg-green-500 text-white' : 'bg-gray-200 text-gray-800'; ?> rounded-lg px-4 py-3">
                                                <p class="text-sm"><?php echo nl2br(htmlspecialchars($msg['message_text'])); ?></p>
                                            </div>
                                            <p class="text-xs text-gray-400 mt-1 <?php echo $is_vendor ? 'text-right' : ''; ?>">
                                                <?php echo date('M d, Y h:i A', strtotime($msg['created_at'])); ?>
                                                <?php if ($is_vendor && $msg['is_read']): ?>
                                                    <i class="fas fa-check-double text-blue-500 ml-1"></i>
                                                <?php endif; ?>
                                            </p>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <!-- Message Input -->
                        <div class="px-6 py-4 border-t border-gray-200 bg-gray-50">
                            <form method="POST" class="flex gap-3">
                                <input type="hidden" name="action" value="send">
                                <input type="hidden" name="conversation_id" value="<?php echo $selected_conversation; ?>">
                                <textarea name="message" rows="2" required
                                    placeholder="Type your message..."
                                    class="flex-1 px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500 resize-none"></textarea>
                                <button type="submit" class="bg-green-600 text-white px-6 py-2 rounded-lg hover:bg-green-700 transition">
                                    <i class="fas fa-paper-plane"></i> Send
                                </button>
                            </form>
                        </div>
                    <?php else: ?>
                        <!-- No Conversation Selected -->
                        <div class="flex items-center justify-center h-full text-gray-400 p-12 text-center">
                            <div>
                                <i class="fas fa-comments text-6xl mb-4"></i>
                                <p class="text-lg">Select a conversation to view messages</p>
                                <?php if (empty($conversations)): ?>
                                    <p class="text-sm mt-2">Customers will be able to message you about your products</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Auto-scroll to bottom of messages
        const messagesContainer = document.querySelector('.messages-container');
        if (messagesContainer) {
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }

        // Auto-refresh messages every 10 seconds if conversation is selected
        <?php if ($selected_conversation > 0): ?>
        setInterval(function() {
            location.reload();
        }, 10000);
        <?php endif; ?>

        // Submit form on Enter (Shift+Enter for new line)
        document.querySelector('textarea[name="message"]')?.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                this.closest('form').submit();
            }
        });
    </script>
    <script src="../js/mobile-nav.js"></script>
</body>
</html>