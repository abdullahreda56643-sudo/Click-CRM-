</div>
            </div>
        </main>
    </div>

    <!-- JavaScript -->
    <script>
        // Sidebar Toggle
        const sidebarToggle = document.getElementById('sidebarToggle');
        const sidebar = document.getElementById('sidebar');
        const sidebarOverlay = document.getElementById('sidebarOverlay');
        
        if (sidebarToggle && sidebar) {
            sidebarToggle.addEventListener('click', () => {
                sidebar.classList.toggle('-translate-x-full');
                sidebarOverlay.classList.toggle('hidden');
            });
        }
        
        if (sidebarOverlay) {
            sidebarOverlay.addEventListener('click', () => {
                sidebar.classList.add('-translate-x-full');
                sidebarOverlay.classList.add('hidden');
            });
        }

        // Notifications Dropdown
        const notificationToggle = document.getElementById('notificationToggle');
        const notificationsDropdown = document.getElementById('notificationsDropdown');
        
        if (notificationToggle && notificationsDropdown) {
            notificationToggle.addEventListener('click', (e) => {
                e.stopPropagation();
                // use 'open' class for custom dropdown panels
                notificationsDropdown.classList.toggle('open');
                
                // Close user dropdown if open
                const userDropdown = document.getElementById('userDropdown');
                if (userDropdown) {
                    userDropdown.classList.remove('open');
                }
            });
        }

        // Fallback handler for profile button (some pages use inline onclick or different IDs)
        (function(){
            const btn = document.getElementById('userMenuBtn') || document.getElementById('userMenuToggle');
            const dropdown = document.getElementById('userDropdown');
            if (!btn) return;
            btn.addEventListener('click', function(e){
                e.stopPropagation();
                // If global helper exists, prefer it
                if (typeof window.toggleDropdown === 'function') {
                    try { window.toggleDropdown('userDropdown'); return; } catch (err) { /* fallback below */ }
                }
                // Fallback: simple toggle and close other panels
                document.querySelectorAll('.dropdown-panel').forEach(panel => {
                    if (panel.id !== 'userDropdown') panel.classList.remove('open');
                });
                if (dropdown) dropdown.classList.toggle('open');
            });
        })();

        // User Menu Dropdown
        const userMenuToggle = document.getElementById('userMenuToggle');
        const userDropdown = document.getElementById('userDropdown');
        
        if (userMenuToggle && userDropdown) {
            userMenuToggle.addEventListener('click', (e) => {
                e.stopPropagation();
                userDropdown.classList.toggle('open');
                
                // Close notifications dropdown if open
                if (notificationsDropdown) {
                    notificationsDropdown.classList.remove('open');
                }
            });
        }

        // Close dropdowns when clicking outside
        document.addEventListener('click', () => {
            if (notificationsDropdown) {
                notificationsDropdown.classList.remove('open');
            }
            if (userDropdown) {
                userDropdown.classList.remove('open');
            }
        });

        // Notification Functions
        function viewNotification(notificationId) {
            // Mark notification as read via AJAX
            fetch('<?php echo base_url('includes/ajax/mark_notification_read.php'); ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    notification_id: notificationId,
                    csrf_token: '<?php echo generate_csrf_token(); ?>'
                })
            })
                .then(response => safeJson(response))
                .then(data => {
                    if (data && data.success) {
                        const notificationElement = document.querySelector(`[onclick="viewNotification(${notificationId})"]`);
                        if (notificationElement) {
                            notificationElement.classList.remove('bg-blue-50');
                            notificationElement.classList.add('bg-gray-50');
                            const unreadDot = notificationElement.querySelector('.bg-blue-500');
                            if (unreadDot) unreadDot.remove();
                        }
                        updateUnreadCount();
                    } else {
                        console.warn('mark notification read returned no data or failed', data);
                    }
                })
                .catch(error => {
                    console.error('Error marking notification as read:', error);
                });
        }

        function markAllNotificationsRead() {
            fetch('<?php echo base_url('includes/ajax/mark_all_notifications_read.php'); ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    csrf_token: '<?php echo generate_csrf_token(); ?>'
                })
            })
                .then(response => safeJson(response))
                .then(data => {
                    if (data && data.success) {
                        const notifications = document.querySelectorAll('#notificationsDropdown .bg-blue-50');
                        notifications.forEach(notification => {
                            notification.classList.remove('bg-blue-50');
                            notification.classList.add('bg-gray-50');
                            const unreadDot = notification.querySelector('.bg-blue-500');
                            if (unreadDot) unreadDot.remove();
                        });
                        updateUnreadCount();
                    } else {
                        console.warn('markAllNotificationsRead returned no data or failed', data);
                    }
                })
                .catch(error => {
                    console.error('Error marking all notifications as read:', error);
                });
        }

        function updateUnreadCount() {
            fetch('<?php echo base_url('includes/ajax/get_unread_count.php'); ?>', {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(safeJson)
            .then(data => {
                const badge = document.querySelector('.notif-badge');
                if (badge) {
                    if (data && data.count > 0) {
                        badge.textContent = data.count;
                        badge.classList.remove('hidden');
                    } else {
                        badge.classList.add('hidden');
                    }
                }
            })
            .catch(error => {
                console.error('Error getting unread count:', error);
            });
        }

        function markAllRead() {
            fetch('<?php echo base_url('includes/ajax/mark_all_notifications_read.php'); ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    csrf_token: '<?php echo generate_csrf_token(); ?>'
                })
            })
                .then(response => safeJson(response))
                .then(data => {
                    if (data && data.success) {
                        const notifications = document.querySelectorAll('#notificationsDropdown .bg-blue-50');
                        notifications.forEach(notification => {
                            notification.classList.remove('bg-blue-50');
                            notification.classList.add('bg-gray-50');
                            const unreadDot = notification.querySelector('.bg-blue-500');
                            if (unreadDot) unreadDot.remove();
                        });
                        updateUnreadCount();
                    } else {
                        console.warn('markAllRead returned no data or failed', data);
                    }
                })
                .catch(error => {
                    console.error('Error marking all notifications as read:', error);
                });
        }

        // Auto-refresh notifications every 30 seconds
        setInterval(updateUnreadCount, 30000);

        // Form validation helpers
        function validateForm(formId) {
            const form = document.getElementById(formId);
            if (!form) return true;
            
            const requiredFields = form.querySelectorAll('[required]');
            let isValid = true;
            
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    field.classList.add('border-red-500');
                    isValid = false;
                } else {
                    field.classList.remove('border-red-500');
                }
            });
            
            return isValid;
        }

        // Loading states
        function setLoading(button, loading = true) {
            if (loading) {
                button.disabled = true;
                button.dataset.originalText = button.textContent;
                button.innerHTML = '<i class="fas fa-spinner fa-spin ml-2"></i> جاري التحميل...';
            } else {
                button.disabled = false;
                button.textContent = button.dataset.originalText;
            }
        }

        // Confirm dialogs
        function confirmAction(message, callback) {
            if (confirm(message)) {
                callback();
            }
        }

        // File upload preview
        function previewFile(input, previewContainer) {
            const file = input.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const preview = document.getElementById(previewContainer);
                    if (preview) {
                        preview.src = e.target.result;
                        preview.classList.remove('hidden');
                    }
                };
                reader.readAsDataURL(file);
            }
        }

        // Date picker initialization (if needed)
        function initDatePickers() {
            const dateInputs = document.querySelectorAll('input[type="date"]');
            dateInputs.forEach(input => {
                // Set max date to today for past dates
                if (input.hasAttribute('data-max-today')) {
                    input.max = new Date().toISOString().split('T')[0];
                }
                
                // Set min date to today for future dates
                if (input.hasAttribute('data-min-today')) {
                    input.min = new Date().toISOString().split('T')[0];
                }
            });
        }

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            initDatePickers();
            
            // Add smooth scrolling
            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', function (e) {
                    const href = this.getAttribute('href') || '';
                    // Only intercept and prevent default for true hash links
                    if (!href.startsWith('#')) return; // allow normal navigation for changed hrefs
                    e.preventDefault();
                    const target = document.querySelector(href);
                    if (target) {
                        target.scrollIntoView({ behavior: 'smooth' });
                    }
                });
            });
        });

        // Global Search Functionality
        const searchInput = document.getElementById('globalSearchInput');
        const searchResults = document.createElement('div');
        searchResults.id = 'searchResults';
        searchResults.className = 'absolute top-full left-0 right-0 mt-2 bg-white rounded-xl shadow-lg border border-gray-200 max-h-80 overflow-y-auto hidden z-50';
        
        if (searchInput) {
            searchInput.parentNode.appendChild(searchResults);
            
            let searchTimeout;
            
            searchInput.addEventListener('input', function(e) {
                clearTimeout(searchTimeout);
                const query = e.target.value.trim();
                
                if (query.length < 2) {
                    searchResults.classList.add('hidden');
                    return;
                }
                
                searchTimeout = setTimeout(() => {
                    performQuickSearch(query);
                }, 300);
            });
            
            searchInput.addEventListener('focus', function() {
                if (this.value.trim().length >= 2) {
                    performQuickSearch(this.value.trim());
                }
            });
            
            searchInput.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    const query = this.value.trim();
                    if (query) {
                        window.location.href = '<?php echo base_url('global_search.php'); ?>?q=' + encodeURIComponent(query);
                    }
                }
                
                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    const firstResult = searchResults.querySelector('.search-result-item');
                    if (firstResult) {
                        firstResult.focus();
                    }
                }
                
                if (e.key === 'Escape') {
                    searchResults.classList.add('hidden');
                    this.blur();
                }
            });
        }
        
        function performQuickSearch(query) {
            fetch('<?php echo base_url('includes/ajax/quick_search.php'); ?>?q=' + encodeURIComponent(query), {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(safeJson)
            .then(data => {
                if (data && Array.isArray(data.results)) {
                    displaySearchResults(data.results, query);
                } else {
                    searchResults.classList.add('hidden');
                }
            })
            .catch(error => {
                console.error('Search error:', error);
                searchResults.classList.add('hidden');
            });
        }
        
        function displaySearchResults(results, query) {
            if (results.length === 0) {
                searchResults.innerHTML = `
                    <div class="p-4 text-center text-gray-500">
                        <i class="fas fa-search text-gray-300 text-2xl mb-2"></i>
                        <p class="text-sm">لا توجد نتائج لـ "${query}"</p>
                    </div>
                `;
            } else {
                const html = results.map(result => {
                    const colorClasses = {
                        blue: 'text-blue-600 bg-blue-50',
                        green: 'text-green-600 bg-green-50',
                        yellow: 'text-yellow-600 bg-yellow-50',
                        red: 'text-red-600 bg-red-50'
                    };
                    
                    return `
                        <a href="${result.url}" class="search-result-item flex items-center gap-3 p-3 hover:bg-gray-50 transition-colors border-b border-gray-100 last:border-b-0" tabindex="0">
                            <div class="w-8 h-8 rounded-lg ${colorClasses[result.color]} flex items-center justify-center flex-shrink-0">
                                <i class="${result.icon} text-sm"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="font-medium text-gray-900 truncate">${result.title}</div>
                                ${result.subtitle ? `<div class="text-sm text-gray-500 truncate">${result.subtitle}</div>` : ''}
                            </div>
                            <i class="fas fa-arrow-left text-gray-400 text-sm"></i>
                        </a>
                    `;
                }).join('');
                
                searchResults.innerHTML = `
                    <div class="p-2">
                        ${html}
                        <div class="border-t border-gray-100 p-2">
                            <a href="<?php echo base_url('global_search.php'); ?>?q=${encodeURIComponent(query)}" class="block text-center text-sm text-[#1e4a76] hover:underline font-medium">
                                عرض جميع نتائج البحث
                            </a>
                        </div>
                    </div>
                `;
            }
            
            searchResults.classList.remove('hidden');
            
            // Add keyboard navigation to results
            const resultItems = searchResults.querySelectorAll('.search-result-item');
            resultItems.forEach((item, index) => {
                item.addEventListener('keydown', function(e) {
                    if (e.key === 'ArrowDown') {
                        e.preventDefault();
                        const nextItem = resultItems[index + 1];
                        if (nextItem) nextItem.focus();
                    } else if (e.key === 'ArrowUp') {
                        e.preventDefault();
                        if (index === 0) {
                            searchInput.focus();
                        } else {
                            const prevItem = resultItems[index - 1];
                            if (prevItem) prevItem.focus();
                        }
                    } else if (e.key === 'Escape') {
                        searchResults.classList.add('hidden');
                        searchInput.focus();
                    }
                });
            });
        }
        
        // Close search results when clicking outside
        document.addEventListener('click', function(e) {
            if (!e.target.closest('#globalSearchInput') && !e.target.closest('#searchResults')) {
                searchResults.classList.add('hidden');
            }
        });
        
        // Mobile search functionality
        function toggleMobileSearch() {
            const mobileSearchModal = document.createElement('div');
            mobileSearchModal.id = 'mobileSearchModal';
            mobileSearchModal.className = 'fixed inset-0 bg-black/50 z-50 flex items-start justify-center pt-20 hidden';
            mobileSearchModal.innerHTML = `
                <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg mx-4">
                    <div class="p-4">
                        <div class="flex items-center gap-3 mb-4">
                            <i class="fas fa-search text-gray-400"></i>
                            <input type="text" 
                                   id="mobileSearchInput"
                                   placeholder="بحث في جهات الاتصال، الصفقات، المهام..." 
                                   class="flex-1 px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-[#1e4a76] focus:border-transparent"
                                   autofocus>
                            <button onclick="closeMobileSearch()" class="text-gray-400 hover:text-gray-600">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <div id="mobileSearchResults" class="max-h-60 overflow-y-auto"></div>
                    </div>
                </div>
            `;
            
            document.body.appendChild(mobileSearchModal);
            mobileSearchModal.classList.remove('hidden');
            
            const mobileInput = document.getElementById('mobileSearchInput');
            mobileInput.focus();
            
            mobileInput.addEventListener('input', function(e) {
                const query = e.target.value.trim();
                if (query.length >= 2) {
                    performMobileSearch(query);
                } else {
                    document.getElementById('mobileSearchResults').innerHTML = '';
                }
            });
            
            mobileInput.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    const query = this.value.trim();
                    if (query) {
                        window.location.href = '<?php echo base_url('global_search.php'); ?>?q=' + encodeURIComponent(query);
                    }
                }
                if (e.key === 'Escape') {
                    closeMobileSearch();
                }
            });
            
            // Close when clicking outside
            mobileSearchModal.addEventListener('click', function(e) {
                if (e.target === mobileSearchModal) {
                    closeMobileSearch();
                }
            });
        }
        
        function closeMobileSearch() {
            const modal = document.getElementById('mobileSearchModal');
            if (modal) {
                modal.remove();
            }
        }
        
        function performMobileSearch(query) {
            fetch('<?php echo base_url('includes/ajax/quick_search.php'); ?>?q=' + encodeURIComponent(query), {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(safeJson)
            .then(data => {
                if (data && Array.isArray(data.results)) {
                    displayMobileSearchResults(data.results, query);
                }
            })
            .catch(error => {
                console.error('Mobile search error:', error);
            });
        }
        
        function displayMobileSearchResults(results, query) {
            const resultsContainer = document.getElementById('mobileSearchResults');
            
            if (results.length === 0) {
                resultsContainer.innerHTML = `
                    <div class="text-center py-4 text-gray-500">
                        <i class="fas fa-search text-gray-300 text-xl mb-2"></i>
                        <p class="text-sm">لا توجد نتائج لـ "${query}"</p>
                    </div>
                `;
            } else {
                const colorClasses = {
                    blue: 'text-blue-600 bg-blue-50',
                    green: 'text-green-600 bg-green-50',
                    yellow: 'text-yellow-600 bg-yellow-50',
                    red: 'text-red-600 bg-red-50'
                };
                
                const html = results.map(result => `
                    <a href="${result.url}" class="flex items-center gap-3 p-3 hover:bg-gray-50 border-b border-gray-100 last:border-b-0">
                        <div class="w-8 h-8 rounded-lg ${colorClasses[result.color]} flex items-center justify-center flex-shrink-0">
                            <i class="${result.icon} text-sm"></i>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="font-medium text-gray-900 truncate">${result.title}</div>
                            ${result.subtitle ? `<div class="text-sm text-gray-500 truncate">${result.subtitle}</div>` : ''}
                        </div>
                    </a>
                `).join('');
                
                resultsContainer.innerHTML = html + `
                    <div class="border-t border-gray-100 p-3">
                        <a href="<?php echo base_url('global_search.php'); ?>?q=${encodeURIComponent(query)}" class="block text-center text-sm text-[#1e4a76] hover:underline font-medium">
                            عرض جميع نتائج البحث
                        </a>
                    </div>
                `;
            }
        }
        
        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl/Cmd + K for search
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                e.preventDefault();
                if (searchInput) {
                    searchInput.focus();
                    searchInput.select();
                }
            }
            
            // Escape to close dropdowns
            if (e.key === 'Escape') {
                if (notificationsDropdown) {
                    notificationsDropdown.classList.add('hidden');
                }
                if (userDropdown) {
                    userDropdown.classList.add('hidden');
                }
                if (sidebar && window.innerWidth < 1024) {
                    sidebar.classList.add('-translate-x-full');
                    sidebarOverlay.classList.add('hidden');
                }
            }
        });

        // Print functionality
        function printPage() {
            window.print();
        }

        // Export functionality
        function exportData(format, module) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '<?php echo base_url('includes/ajax/export.php'); ?>';
            
            const formatInput = document.createElement('input');
            formatInput.type = 'hidden';
            formatInput.name = 'format';
            formatInput.value = format;
            form.appendChild(formatInput);
            
            const moduleInput = document.createElement('input');
            moduleInput.type = 'hidden';
            moduleInput.name = 'module';
            moduleInput.value = module;
            form.appendChild(moduleInput);
            
            const csrfInput = document.createElement('input');
            csrfInput.type = 'hidden';
            csrfInput.name = 'csrf_token';
            csrfInput.value = '<?php echo generate_csrf_token(); ?>';
            form.appendChild(csrfInput);
            
            document.body.appendChild(form);
            form.submit();
            document.body.removeChild(form);
        }

        // AJAX helper
        function ajaxRequest(url, data, method = 'POST') {
            return fetch(url, {
                method: method,
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(data)
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return safeJson(response);
            });
        }

        // Safely parse JSON responses; if server returned HTML (e.g., PHP warning), log and return null
        function safeJson(response) {
            return response.text().then(text => {
                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.error('Invalid JSON response from', response.url, text);
                    return null;
                }
            });
        }

        // Dropdown toggle function with suppression to avoid immediate outside-click race
        window.__suppressDropdownClose = false;
        window.toggleDropdown = function(dropdownId) {
            const dropdown = document.getElementById(dropdownId);
            if (!dropdown) return;

            // Prevent document click handler from immediately closing
            window.__suppressDropdownClose = true;
            setTimeout(() => { window.__suppressDropdownClose = false; }, 150);

            // Close all other dropdowns
            document.querySelectorAll('.dropdown-panel').forEach(panel => {
                if (panel.id !== dropdownId) {
                    panel.classList.remove('open');
                }
            });

            // Toggle current dropdown
            dropdown.classList.toggle('open');
        }

        // Close dropdowns when clicking outside
        document.addEventListener('click', function(e) {
            if (window.__suppressDropdownClose) return;
            if (!e.target.closest('.dropdown-panel') && !e.target.closest('[onclick*="toggleDropdown"]')) {
                document.querySelectorAll('.dropdown-panel').forEach(panel => {
                    panel.classList.remove('open');
                });
            }
        });

        // Toast notifications (side, animated)
        (function initToastStyles(){
            const css = `
                #toastContainer { position: fixed; top: 72px; right: 24px; display: flex; flex-direction: column; gap: 12px; z-index: 99999; }
                @media (max-width: 640px) { #toastContainer { right: 12px; left: 12px; top: 16px; } }
                .toast-item { display: flex; align-items: center; gap: 12px; min-width: 220px; max-width: 420px; padding: 12px 14px; border-radius: 12px; box-shadow: 0 10px 30px rgba(2,6,23,0.12); color: white; transform: translateX(100%); opacity: 0; }
                .toast-item .toast-icon { width: 36px; height: 36px; border-radius: 9px; display:flex; align-items:center; justify-content:center; font-size:14px; opacity:0.95 }
                .toast-item .toast-message { flex: 1; font-weight: 600; }
                .toast-item.show { transform: translateX(0); opacity: 1; transition: transform 360ms cubic-bezier(.16,1,.3,1), opacity 360ms ease; }
                .toast-item.hide { transform: translateX(100%); opacity: 0; transition: transform 360ms cubic-bezier(.16,1,.3,1), opacity 360ms ease; }
                .toast-success { background: linear-gradient(90deg,#10b981,#059669); }
                .toast-error { background: linear-gradient(90deg,#ef4444,#dc2626); }
                .toast-warning { background: linear-gradient(90deg,#f59e0b,#d97706); }
                .toast-info { background: linear-gradient(90deg,#2563eb,#1e40af); }
                .toast-close { background: rgba(255,255,255,0.06); border-radius: 8px; width: 28px; height: 28px; display:flex;align-items:center;justify-content:center;cursor:pointer }
            `;
            const style = document.createElement('style');
            style.appendChild(document.createTextNode(css));
            document.head.appendChild(style);
        })();

        function ensureToastContainer() {
            let c = document.getElementById('toastContainer');
            if (!c) {
                c = document.createElement('div');
                c.id = 'toastContainer';
                document.body.appendChild(c);
            }
            return c;
        }

        function showToast(message, type = 'info', options = {}) {
            const container = ensureToastContainer();
            const toast = document.createElement('div');
            toast.className = 'toast-item toast-' + (type || 'info');

            const iconMap = {
                success: '<i class="fas fa-check"></i>',
                error: '<i class="fas fa-times"></i>',
                warning: '<i class="fas fa-exclamation"></i>',
                info: '<i class="fas fa-info"></i>'
            };

            toast.innerHTML = `
                <div class="toast-icon">${iconMap[type] || iconMap.info}</div>
                <div class="toast-message">${String(message)}</div>
                <div class="toast-close" aria-label="close">&times;</div>
            `;

            const closeBtn = toast.querySelector('.toast-close');
            closeBtn.addEventListener('click', () => removeToast(toast));

            container.appendChild(toast);

            // Force reflow then show
            void toast.offsetWidth;
            toast.classList.add('show');

            const ttl = typeof options.ttl === 'number' ? options.ttl : 4000;
            const timer = setTimeout(() => removeToast(toast), ttl);

            function removeToast(node) {
                clearTimeout(timer);
                node.classList.remove('show');
                node.classList.add('hide');
                setTimeout(() => {
                    if (node.parentNode) node.parentNode.removeChild(node);
                }, 380);
            }
        }

        // If server provided flash messages, show them as unified toasts
        (function() {
            try {
                if (window.__FLASH_MESSAGES && Array.isArray(window.__FLASH_MESSAGES)) {
                    window.__FLASH_MESSAGES.forEach((m, idx) => {
                        setTimeout(() => {
                            showToast(m.message || '', m.type || 'info');
                        }, idx * 600);
                    });
                    // Clear after showing to avoid duplicates
                    window.__FLASH_MESSAGES = [];
                }
            } catch (e) {
                console.error('Flash toast error', e);
            }
        })();
    </script>
</body>
</html>