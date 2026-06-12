<div id="metaPostPickerModal" class="fixed inset-0 bg-black/60 z-[10000] hidden items-center justify-center backdrop-blur-sm">
    <div class="bg-white rounded-2xl w-[90%] max-w-2xl max-h-[85vh] flex flex-col shadow-2xl overflow-hidden shadow-black/20">
        
        <!-- Modal Header -->
        <div class="p-5 border-b border-gray-100 flex items-center justify-between bg-gray-50/50">
            <div>
                <h3 class="text-lg font-bold text-gray-900" id="mppModalTitle">اختر منشوراً</h3>
                <p class="text-sm text-gray-500 mt-1">اختر المنشور لربطه بهذا المنتج ليتم الرد على تعليقاته تلقائياً</p>
            </div>
            <button type="button" onclick="closePostPickerModal()" class="w-8 h-8 flex items-center justify-center rounded-full bg-gray-100 text-gray-500 hover:bg-gray-200 hover:text-gray-900 transition-colors">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <!-- Modal Body & loader -->
        <div class="p-5 overflow-y-auto flex-1 relative bg-gray-50" id="mppModalBody">
            <div id="mppLoader" class="absolute inset-0 bg-gray-50/80 flex flex-col items-center justify-center z-10 hidden">
                <div class="w-10 h-10 border-4 border-[#00A8E8]/20 border-t-[#00A8E8] rounded-full animate-spin"></div>
                <span class="mt-4 text-sm font-medium text-gray-600">جاري جلب المنشورات...</span>
            </div>
            
            <div id="mppError" class="hidden mb-4 p-4 bg-red-50 text-red-700 rounded-xl text-sm border border-red-100 flex items-center gap-2">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                <span id="mppErrorText"></span>
            </div>

            <!-- Posts Grid -->
            <div id="mppGrid" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <!-- Javascript will inject posts here -->
            </div>
        </div>
    </div>
</div>

<script>
    let currentPlatform = 'facebook';
    let targetInputId = '';

    function openPostPickerModal(platform, inputId) {
        currentPlatform = platform;
        targetInputId = inputId;
        
        document.getElementById('mppModalTitle').innerText = platform === 'facebook' ? 'اختر منشور فيسبوك' : 'اختر منشور انستقرام';
        
        const modal = document.getElementById('metaPostPickerModal');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        document.body.style.overflow = 'hidden';

        fetchPosts(platform);
    }

    function closePostPickerModal() {
        const modal = document.getElementById('metaPostPickerModal');
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        document.body.style.overflow = '';
    }

    async function fetchPosts(platform) {
        const loader = document.getElementById('mppLoader');
        const errorDiv = document.getElementById('mppError');
        const grid = document.getElementById('mppGrid');
        
        loader.classList.remove('hidden');
        errorDiv.classList.add('hidden');
        grid.innerHTML = '';

        try {
            const response = await fetch(`/customer/social-accounts/meta-posts?platform=${platform}`, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            });

            const result = await response.json();

            if (!response.ok) {
                throw new Error(result.error || 'حدث خطأ أثناء جلب المنشورات');
            }

            if (!result.data || result.data.length === 0) {
                grid.innerHTML = `
                    <div class="col-span-full py-12 flex flex-col items-center justify-center text-gray-500 bg-white rounded-2xl border border-gray-100">
                        <svg class="w-12 h-12 mb-3 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m6.75 12l-3-3m0 0l-3 3m3-3v6m-1.5-15H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
                        <p>لا توجد منشورات متاحة</p>
                    </div>`;
                return;
            }

            renderPosts(result.data, platform);

        } catch (err) {
            errorDiv.classList.remove('hidden');
            document.getElementById('mppErrorText').innerText = err.message;
        } finally {
            loader.classList.add('hidden');
        }
    }

    function renderPosts(posts, platform) {
        const grid = document.getElementById('mppGrid');
        
        posts.forEach(post => {
            let imgUrl = null;
            let text = post.message || post.caption || 'لا يوجد نص';
            let permalink = post.permalink_url || post.permalink || '';

            if (platform === 'instagram') {
                imgUrl = post.media_type === 'VIDEO' ? post.thumbnail_url : post.media_url;
            } else {
                imgUrl = post.full_picture;
            }

            const div = document.createElement('div');
            div.className = 'group bg-white rounded-xl border border-gray-200 overflow-hidden hover:border-[#00A8E8] hover:shadow-md transition-all cursor-pointer flex flex-col';
            div.onclick = () => selectPost(permalink, post.id);

            const imgHtml = imgUrl 
                ? `<div class="aspect-square bg-gray-100 overflow-hidden relative">
                    <img src="${imgUrl}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" loading="lazy">
                   </div>`
                : `<div class="aspect-video bg-gray-50 flex flex-col items-center justify-center text-gray-300 border-b border-gray-100">
                    <svg class="w-10 h-10 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 001.5-1.5V6a1.5 1.5 0 00-1.5-1.5H3.75A1.5 1.5 0 002.25 6v12a1.5 1.5 0 001.5 1.5zm10.5-11.25h.008v.008h-.008V8.25zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z"/></svg>
                    <span class="text-xs">نص فقط</span>
                   </div>`;

            div.innerHTML = `
                ${imgHtml}
                <div class="p-3 flex-1 flex flex-col">
                    <p class="text-sm text-gray-700 line-clamp-2 mb-2 flex-1 break-words">${text}</p>
                    <div class="flex items-center justify-between mt-2 pt-2 border-t border-gray-50">
                        <span class="text-[10px] text-gray-400 font-medium">${new Date(post.created_time || post.timestamp).toLocaleDateString()}</span>
                        <div class="w-6 h-6 rounded-full bg-gray-50 flex items-center justify-center group-hover:bg-[#00A8E8] group-hover:text-white transition-colors">
                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4.5 12.75l6 6 9-13.5"/></svg>
                        </div>
                    </div>
                </div>
            `;
            grid.appendChild(div);
        });
    }

    function selectPost(url, id) {
        const input = document.getElementById(targetInputId);
        if (input) {
            input.value = url || id; // Prioritize URL for user visibility, backend handles URL -> ID parsing
            
            // Dispatch input event to trigger any Live Preview updates we add
            input.dispatchEvent(new Event('input', { bubbles: true }));
            
            // Visual confirmation
            const wrapper = input.closest('.form-group-new');
            if(wrapper) {
                wrapper.style.transition = 'all 0.3s';
                wrapper.style.backgroundColor = '#f0fdf4';
                setTimeout(() => wrapper.style.backgroundColor = '', 1000);
            }
        }
        closePostPickerModal();
    }

    // Setup Live Preview listeners for manual input
    document.addEventListener('DOMContentLoaded', () => {
        ['facebook_post_url', 'instagram_post_url'].forEach(id => {
            const input = document.getElementById(id);
            if (!input) return;

            let timeout = null;
            input.addEventListener('input', (e) => {
                clearTimeout(timeout);
                const url = e.target.value.trim();
                
                // Remove existing preview if empty
                const existingPreview = input.parentNode.querySelector('.live-post-preview');
                if (!url) {
                    if (existingPreview) existingPreview.remove();
                    return;
                }

                // Add loading indicator
                if (!existingPreview) {
                    const previewDiv = document.createElement('div');
                    previewDiv.className = 'live-post-preview mt-2 p-3 bg-gray-50 border border-gray-100 rounded-xl flex items-center gap-3 animate-pulse';
                    previewDiv.innerHTML = `
                        <div class="w-12 h-12 bg-gray-200 rounded-lg"></div>
                        <div class="flex-1 space-y-2">
                            <div class="h-3 bg-gray-200 rounded w-3/4"></div>
                            <div class="h-3 bg-gray-200 rounded w-1/2"></div>
                        </div>
                    `;
                    input.parentNode.appendChild(previewDiv);
                } else {
                    existingPreview.classList.add('animate-pulse');
                }

                timeout = setTimeout(() => validateUrlPreview(url, id === 'facebook_post_url' ? 'facebook' : 'instagram', input), 800);
            });
            
            // Trigger check for old values on load
            if (input.value) {
                input.dispatchEvent(new Event('input', { bubbles: true }));
            }
        });
    });

    async function validateUrlPreview(url, platform, inputEl) {
        const previewDiv = inputEl.parentNode.querySelector('.live-post-preview');
        if (!previewDiv) return;
        
        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
            const response = await fetch('/customer/social-accounts/resolve-url', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ url, platform })
            });

            const result = await response.json();
            previewDiv.classList.remove('animate-pulse');

            if (!response.ok) {
                previewDiv.className = 'live-post-preview mt-2 p-3 bg-red-50 border border-red-100 rounded-xl flex items-center gap-2 text-sm text-red-600';
                previewDiv.innerHTML = `
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    <span>${result.error || 'لم نتمكن من التعرف على الرابط.'} سيقوم النظام بمحاولة التتبع عند استلام تعليق.</span>
                `;
                return;
            }

            const post = result.data;
            let imgUrl = platform === 'instagram' 
                ? (post.media_type === 'VIDEO' ? post.thumbnail_url : post.media_url)
                : post.full_picture;
            
            let text = post.message || post.caption || 'تم الربط بنجاح (بدون نص)';

            previewDiv.className = 'live-post-preview mt-2 p-2 bg-green-50 border border-green-200 rounded-xl flex items-center gap-3 transition-all';
            let mediaHtml = imgUrl 
                ? `<img src="${imgUrl}" class="w-12 h-12 object-cover rounded-lg border border-green-100">`
                : `<div class="w-12 h-12 bg-green-100 flex items-center justify-center rounded-lg border border-green-200 text-green-600">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                   </div>`;

            previewDiv.innerHTML = `
                ${mediaHtml}
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-green-800 line-clamp-1 truncate">${text}</p>
                    <p class="text-xs text-green-600 mt-0.5">منشور صالح وجاهز للرد التلقائي</p>
                </div>
            `;

        } catch (err) {
            previewDiv.remove();
        }
    }
</script>
