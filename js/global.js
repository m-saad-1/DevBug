// Global JavaScript for all pages

document.addEventListener('DOMContentLoaded', function() {
    // Button hover effects
    const buttons = document.querySelectorAll('.btn');
    buttons.forEach(button => {
        button.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-2px)';
        });
        
        button.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
        });
    });
    
    // Initialize tooltips
    initTooltips();
    
    // Initialize notifications
    initNotifications();

    // Toggle dropdown menu
    const userProfile = document.getElementById('userProfile');
    const dropdownMenu = document.getElementById('dropdownMenu');
    
    if (userProfile && dropdownMenu) {
        userProfile.addEventListener('click', function(e) {
            e.stopPropagation();
            dropdownMenu.classList.toggle('show');
        });
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (dropdownMenu.classList.contains('show') && !userProfile.contains(e.target)) {
                dropdownMenu.classList.remove('show');
            }
        });
    }
    
    // Notification button functionality
    const notificationBtn = document.querySelector('.notification-btn');
    if (notificationBtn) {
        notificationBtn.addEventListener('click', function() {
            alert('You have 3 unread notifications');
        });
    }

    // Comments Section Logic
    const commentsSection = document.querySelector('.comments-section');
    if (commentsSection) {
        // Use event delegation for all actions inside the comments section
        commentsSection.addEventListener('click', function(e) {
            // Edit Comment
            if (e.target.closest('.edit-comment-btn')) {
                const button = e.target.closest('.edit-comment-btn');
                const commentId = button.dataset.commentId;
                const commentDiv = document.getElementById('comment-' + commentId);
                const commentTextDiv = commentDiv.querySelector('.comment-text');
                const originalContent = commentTextDiv.innerHTML;

                // Replace text with textarea
                commentTextDiv.innerHTML = `
                    <textarea class="form-control" style="min-height: 80px;">${commentTextDiv.innerText}</textarea>
                    <div style="margin-top: 10px; display: flex; gap: 10px;">
                        <button class="btn btn-primary btn-sm save-edit-btn">Save</button>
                        <button class="btn btn-secondary btn-sm cancel-edit-btn">Cancel</button>
                    </div>
                `;

                // Add event listeners for save/cancel
                commentDiv.querySelector('.save-edit-btn').addEventListener('click', function() {
                    const newContent = commentDiv.querySelector('textarea').value;
                    fetch(window.location.pathname + window.location.search, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: `edit_comment=1&comment_id=${commentId}&content=${encodeURIComponent(newContent)}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            commentTextDiv.innerHTML = data.content;
                        } else {
                            alert('Error: ' + data.error);
                            commentTextDiv.innerHTML = originalContent;
                        }
                    });
                });

                commentDiv.querySelector('.cancel-edit-btn').addEventListener('click', function() {
                    commentTextDiv.innerHTML = originalContent;
                });
            }

            // Delete Comment
            if (e.target.closest('.delete-comment-btn')) {
                const button = e.target.closest('.delete-comment-btn');
                const commentId = button.dataset.commentId;
                if (confirm('Are you sure you want to delete this comment?')) {
                    fetch(window.location.pathname + window.location.search, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: `delete_comment=1&comment_id=${commentId}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            document.getElementById('comment-' + commentId).remove();
                        } else {
                            alert('Error: ' + data.error);
                        }
                    });
                }
            }

            // Edit Reply
            if (e.target.closest('.edit-reply-btn')) {
                const button = e.target.closest('.edit-reply-btn');
                const replyId = button.dataset.replyId;
                const replyDiv = document.getElementById('reply-' + replyId);
                const replyTextDiv = replyDiv.querySelector('.reply-text');
                const originalContent = replyTextDiv.innerHTML;

                replyTextDiv.innerHTML = `
                    <textarea class="form-control" style="min-height: 60px;">${replyTextDiv.innerText}</textarea>
                    <div style="margin-top: 10px; display: flex; gap: 10px;">
                        <button class="btn btn-primary btn-sm save-reply-btn">Save</button>
                        <button class="btn btn-secondary btn-sm cancel-reply-btn">Cancel</button>
                    </div>
                `;

                replyDiv.querySelector('.save-reply-btn').addEventListener('click', function() {
                    const newContent = replyDiv.querySelector('textarea').value;
                    fetch(window.location.pathname + window.location.search, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: `edit_reply=1&reply_id=${replyId}&content=${encodeURIComponent(newContent)}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            replyTextDiv.innerHTML = data.content;
                        } else {
                            alert('Error: ' + data.error);
                            replyTextDiv.innerHTML = originalContent;
                        }
                    });
                });

                replyDiv.querySelector('.cancel-reply-btn').addEventListener('click', function() {
                    replyTextDiv.innerHTML = originalContent;
                });
            }

            // Delete Reply
            if (e.target.closest('.delete-reply-btn')) {
                const button = e.target.closest('.delete-reply-btn');
                const replyId = button.dataset.replyId;
                if (confirm('Are you sure you want to delete this reply?')) {
                    fetch(window.location.pathname + window.location.search, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: `delete_reply=1&reply_id=${replyId}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            document.getElementById('reply-' + replyId).remove();
                        } else {
                            alert('Error: ' + data.error);
                        }
                    });
                }
            }
        });

        commentsSection.addEventListener('submit', function(e) {
            console.log('Submit event triggered');
            if (e.target.classList.contains('comment-submit-form')) {
                e.preventDefault();
                console.log('Form submission intercepted');
                const form = e.target;
                const formData = new FormData(form);
                const submitButton = form.querySelector('button[type="submit"]');
                const originalButtonText = submitButton.innerHTML;
                const bugId = document.getElementById('comments').dataset.bugId;

                submitButton.disabled = true;
                submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Posting...';

                console.log('Sending fetch request...');
                fetch(`post-details.php?id=${bugId}`, {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: formData
                })
                .then(response => {
                    console.log('Received response:', response);
                    return response.json();
                })
                .then(data => {
                    console.log('Received data:', data);
                    if (data.success) {
                        if (data.isReply) {
                            const replyContainer = document.getElementById(`comment-${data.parentId}-replies-container`);
                            replyContainer.insertAdjacentHTML('beforeend', data.html);
                            const replyForm = document.getElementById(`reply-form-${data.parentId}`);
                            if(replyForm){
                                replyForm.style.display = 'none';
                            }

                        } else {
                            const commentsList = document.getElementById('commentsList');
                            const noCommentsMessage = document.getElementById('no-comments-message');
                            if (noCommentsMessage) {
                                noCommentsMessage.remove();
                            }
                            commentsList.insertAdjacentHTML('afterbegin', data.html);
                            const newComment = commentsList.firstElementChild;
                            newComment.style.backgroundColor = 'rgba(99, 102, 241, 0.1)';
                            setTimeout(() => {
                                newComment.style.backgroundColor = '';
                            }, 2000);
                        }
                        form.querySelector('textarea').value = '';
                    } else {
                        alert('Error: ' + (data.error || 'Could not post comment.'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('A network error occurred. Please try again.');
                })
                .finally(() => {
                    submitButton.disabled = false;
                    submitButton.innerHTML = originalButtonText;
                });
            }
        });
    }

    // Load More Comments
    const loadMoreBtn = document.querySelector('.load-more-btn');
    if (loadMoreBtn) {
        loadMoreBtn.addEventListener('click', function() {
            const bugId = this.dataset.bugId;
            let offset = parseInt(this.dataset.offset);
            const limit = 10;

            this.disabled = true;
            this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';

            fetch(`post-details.php?id=${bugId}&load_comments=1&offset=${offset}&limit=${limit}`, {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const commentsList = document.getElementById('commentsList');
                    commentsList.insertAdjacentHTML('beforeend', data.html);
                    offset += limit;
                    this.dataset.offset = offset;
                    if (!data.hasMore) {
                        this.remove();
                    }
                } else {
                    alert('Error: ' + (data.error || 'Could not load more comments.'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('A network error occurred. Please try again.');
            })
            .finally(() => {
                this.disabled = false;
                this.innerHTML = 'Load More Comments';
            });
        });
    }
});

function initTooltips() {
    // Tooltip initialization logic
    const tooltipElements = document.querySelectorAll('[data-tooltip]');
    tooltipElements.forEach(el => {
        el.addEventListener('mouseenter', showTooltip);
        el.addEventListener('mouseleave', hideTooltip);
    });
}

function showTooltip(e) {
    // Tooltip display logic
    const tooltipText = this.getAttribute('data-tooltip');
    // Implementation would go here
}

function hideTooltip() {
    // Tooltip hide logic
}

function initNotifications() {
    // Notification system initialization
}

// Utility functions
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

function formatDate(date) {
    // Date formatting logic
    return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    });
}