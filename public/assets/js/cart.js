// Global State
let currentMenu = 'plats';
let currentProduct = null;
let cart = JSON.parse(localStorage.getItem('restaurantCart')) || [];
let selectedOptions = [];
let selectedParfums = [];

document.addEventListener('DOMContentLoaded', function () {
    // Initial Render
    updateCartBadge();
    updateThemeIcon();

    // URL Params
    const urlParams = new URLSearchParams(window.location.search);
    const menuParam = urlParams.get('menu');
    if (menuParam === 'plats' || menuParam === 'bar') {
        selectMenu(menuParam);
    }
});

// ─── Thème clair / sombre (blanc par défaut, mémorisé) ───
function currentTheme() {
    try {
        return localStorage.getItem('opera-theme') || 'light';
    } catch (e) {
        return document.documentElement.classList.contains('dark') ? 'dark' : 'light';
    }
}

function applyTheme(theme) {
    document.documentElement.classList.toggle('dark', theme === 'dark');
    try {
        localStorage.setItem('opera-theme', theme);
    } catch (e) {}
    updateThemeIcon();
}

function toggleTheme() {
    applyTheme(currentTheme() === 'dark' ? 'light' : 'dark');
}

function updateThemeIcon() {
    const dark = currentTheme() === 'dark';
    const moon = document.getElementById('themeIconMoon');
    const sun = document.getElementById('themeIconSun');
    if (moon) moon.classList.toggle('hidden', dark);
    if (sun) sun.classList.toggle('hidden', !dark);
}

function showHome() {
    document.getElementById('homePage').classList.remove('hidden');
    document.getElementById('menuPage').classList.add('hidden');
}

function selectMenu(menu) {
    currentMenu = menu;
    document.getElementById('homePage').classList.add('hidden');
    document.getElementById('menuPage').classList.remove('hidden');
    document.getElementById('menuTitle').textContent = menu === 'plats' ? 'Menu Plats' : 'Menu Bar';
    const d = document.getElementById('searchInput');
    const m = document.getElementById('searchInputMobile');
    if (d) d.value = '';
    if (m) m.value = '';
    renderMenu();
    window.scrollTo({ top: 0 });
}

function toggleCategoryNav() {
    const nav = document.getElementById('categoryNav');
    const overlay = document.getElementById('categoryOverlay');

    if (nav.classList.contains('translate-x-0')) {
        closeCategoryNav();
    } else {
        nav.classList.remove('translate-x-full');
        nav.classList.add('translate-x-0');
        overlay.classList.remove('hidden');
    }
}

function closeCategoryNav() {
    const nav = document.getElementById('categoryNav');
    const overlay = document.getElementById('categoryOverlay');
    if (nav) {
        nav.classList.remove('translate-x-0');
        nav.classList.add('translate-x-full');
    }
    if (overlay) overlay.classList.add('hidden');
}

// Slug robuste (accents, espaces, apostrophes) : "Bières pression" -> "cat-bieres-pression"
function catSlug(cat) {
    const s = (cat || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '')
        .replace(/[^a-zA-Z0-9]+/g, '-').replace(/^-+|-+$/g, '').toLowerCase();
    return 'cat-' + (s || 'divers');
}

function scrollToCategory(categoryId) {
    closeCategoryNav();
    const el = document.getElementById(categoryId);
    if (!el) return;
    // Compense le header sticky (barre marque + pills) pour ne pas masquer la section
    const header = document.querySelector('#menuPage header');
    const offset = (header ? header.offsetHeight : 130) + 12;
    const top = el.getBoundingClientRect().top + window.scrollY - offset;
    window.scrollTo({ top: Math.max(0, top), behavior: 'smooth' });
}

function menuSearchQuery() {
    const d = document.getElementById('searchInput');
    const m = document.getElementById('searchInputMobile');
    const q = ((d && d.value) || '') + ' ' + ((m && m.value) || '');
    return q.trim().toLowerCase();
}

function onSearchInput() {
    // Sync les deux champs recherche (desktop / mobile)
    const active = document.activeElement;
    const d = document.getElementById('searchInput');
    const m = document.getElementById('searchInputMobile');
    if (active === d && m) m.value = d.value;
    if (active === m && d) d.value = m.value;
    renderMenu();
}

function dishPriceLabel(p) {
    if (p.attributes && p.attributes.manual_variations) {
        const prices = p.attributes.manual_variations.items.map(i => i.price).sort((a, b) => a - b);
        return `dès ${prices[0].toLocaleString('fr-FR')} F`;
    }
    return parseFloat(p.price).toLocaleString('fr-FR') + ' F';
}

function renderMenu() {
    const query = menuSearchQuery();
    const baseFiltered = productsData.filter(p => p.menu === currentMenu);
    const filteredProducts = query
        ? baseFiltered.filter(p => ((p.name || '') + ' ' + (p.description || '') + ' ' + (p.category || '')).toLowerCase().includes(query))
        : baseFiltered;
    const categories = [...new Set(filteredProducts.map(p => p.category))];
    const allCategories = [...new Set(baseFiltered.map(p => p.category))];

    // Drawer catégories (desktop)
    const categoryList = document.getElementById('categoryList');
    if (categoryList) {
        const cats = query ? categories : allCategories;
        categoryList.innerHTML = cats.map(cat => `
            <button onclick="scrollToCategory('${catSlug(cat)}')"
                    class="w-full text-left px-4 py-3 text-stone-700 dark:text-stone-200 hover:bg-primary-50 dark:hover:bg-white/10 hover:text-primary-700 dark:hover:text-white rounded-xl smooth-transition font-medium text-sm">
                ${cat}
            </button>
        `).join('') || '<p class="text-sm text-stone-400 italic px-4">Aucune catégorie</p>';
    }

    // Pills (toutes tailles)
    const pills = document.getElementById('categoryPills');
    if (pills) {
        const cats = query ? categories : allCategories;
        pills.innerHTML = cats.map(cat => `
            <button onclick="scrollToCategory('${catSlug(cat)}')"
                    class="whitespace-nowrap px-4 py-1.5 bg-stone-100 text-navy-800 border border-stone-200 rounded-full text-xs font-bold hover:bg-primary-500 hover:text-white hover:border-primary-500 smooth-transition dark:bg-white/10 dark:text-stone-200 dark:border-white/10 dark:hover:bg-primary-500">
                ${cat}
            </button>
        `).join('');
    }

    // Ancien menu mobile bas (conservé si présent)
    const mobileCategoryNav = document.getElementById('mobileCategoryNav');
    if (mobileCategoryNav) {
        mobileCategoryNav.classList.add('hidden');
    }

    const menuGrid = document.getElementById('menuGrid');
    menuGrid.innerHTML = categories.map(category => {
        const categoryProducts = filteredProducts.filter(p => p.category === category);
        return `
    <section id="${catSlug(category)}" class="scroll-mt-36 fade-in">
        <div class="flex items-end justify-between mb-6">
            <div>
                <p class="text-[11px] font-bold uppercase tracking-[0.25em] text-primary-600 dark:text-primary-500 mb-1">${currentMenu === 'plats' ? 'Carte' : 'Bar'}</p>
                <h2 class="font-serif-d text-3xl md:text-4xl font-semibold text-stone-900 dark:text-white">${category}</h2>
            </div>
            <span class="text-xs font-bold text-navy-700 bg-navy-50 border border-navy-100 dark:bg-white/10 dark:text-stone-300 dark:border-white/10 rounded-full px-3 py-1.5 whitespace-nowrap">${categoryProducts.length} plat${categoryProducts.length > 1 ? 's' : ''}</span>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
            ${categoryProducts.map(p => {
            // Escape single quotes for JSON
            const pJson = JSON.stringify(p).replace(/'/g, "&#39;");
            return `
                <button onclick='showProductModal(${pJson})'
                        class="text-left bg-white dark:bg-white/[0.04] rounded-3xl overflow-hidden card-hover border border-stone-200/80 dark:border-white/10 w-full flex flex-col">
                    ${p.image ? `
                        <div class="relative w-full h-44 bg-stone-200 overflow-hidden">
                            <img src="${p.image}"
                                 alt=""
                                 class="w-full h-full object-cover"
                                 loading="lazy"
                                 onerror="this.parentElement.style.display='none'">
                            <span class="absolute bottom-3 right-3 bg-ink-950/90 backdrop-blur text-white text-sm font-bold rounded-full px-3.5 py-1.5">${dishPriceLabel(p)}</span>
                        </div>
                    ` : ''}
                    <div class="p-5 flex flex-col flex-1">
                        <h3 class="font-serif-d font-semibold text-stone-900 dark:text-white mb-1.5 text-xl leading-snug">${p.name}</h3>
                        <p class="text-stone-500 dark:text-stone-400 text-sm mb-4 line-clamp-2 leading-relaxed flex-1">${p.description || ''}</p>
                        <div class="flex items-center justify-between pt-4 border-t border-stone-100 dark:border-white/10">
                            ${p.image ? `
                                <span class="text-primary-600 text-sm font-bold inline-flex items-center gap-1">Personnaliser <span aria-hidden="true">→</span></span>
                            ` : `
                                <span class="font-serif-d text-2xl font-bold text-stone-900 dark:text-white">${dishPriceLabel(p)}</span>
                                <span class="w-10 h-10 rounded-full bg-primary-500 text-white flex items-center justify-center font-bold text-xl leading-none">+</span>
                            `}
                        </div>
                    </div>
                </button>
            `}).join('')}
        </div>
    </section>
`;
    }).join('');

    const emptyEl = document.getElementById('emptySearch');
    if (emptyEl) emptyEl.classList.toggle('hidden', categories.length > 0);

    updateCartBadge();
    if (window.lucide) lucide.createIcons();
}

function showProductModal(product) {
    currentProduct = JSON.parse(JSON.stringify(product));
    selectedOptions = [];
    selectedParfums = [];

    // Poissons logic: Check if variations exist
    if (product.category === 'Poissons' && (!product.attributes || !product.attributes.manual_variations || product.attributes.manual_variations.items.length === 0)) {
        alert("Désolé, aucun poisson n'est disponible pour le moment.");
        return;
    }

    document.getElementById('modalProductName').textContent = product.name;
    document.getElementById('modalProductDescription').textContent = product.description || '';
    const modalCat = document.getElementById('modalProductCategory');
    if (modalCat) modalCat.textContent = product.category || '';
    const modalImgWrap = document.getElementById('modalImageWrap');
    const modalImg = document.getElementById('modalImage');
    if (modalImgWrap && modalImg) {
        if (product.image) {
            modalImg.src = product.image;
            modalImgWrap.classList.remove('hidden');
        } else {
            modalImg.removeAttribute('src');
            modalImgWrap.classList.add('hidden');
        }
    }

    const attributesHtml = [];

    // Manual Variations
    if (product.attributes && product.attributes.manual_variations) {
        const variations = product.attributes.manual_variations;
        attributesHtml.push(`
    <div class="space-y-3">
        <div class="flex items-center gap-2">
            <h3 class="text-xs font-bold text-stone-800 dark:text-stone-200 uppercase tracking-[0.15em]">${variations.title}</h3>
            <span class="text-xs text-red-500 font-medium">* Obligatoire</span>
        </div>
        <div class="space-y-2">
            ${variations.items.map((item, index) => `
                <label class="flex items-center justify-between p-3.5 bg-stone-50 dark:bg-white/5 border border-stone-200 dark:border-white/10 rounded-2xl cursor-pointer hover:border-primary-500 hover:bg-primary-50/50 smooth-transition">
                    <div class="flex items-center gap-3">
                        <input type="radio" 
                               name="manual_variation" 
                               value="${index}" 
                               data-price="${item.price}"
                               data-name="${item.name}"
                               ${index === 0 ? 'checked' : ''}
                               onchange="handleManualVariationChange(this)"
                               class="w-4 h-4">
                        <span class="text-gray-700 dark:text-stone-200 text-sm font-medium">${item.name}</span>
                    </div>
                    <span class="text-gray-900 dark:text-white font-bold">${parseFloat(item.price).toLocaleString('fr-FR')} F</span>
                </label>
            `).join('')}
        </div>
    </div>
`);
        // Init default
        currentProduct.price = variations.items[0].price;
        currentProduct.selectedVariation = {
            name: variations.items[0].name,
            price: variations.items[0].price,
            isManual: true
        };
        currentProduct.is_variable = true;
    }

    // Custom Options
    if (product.attributes && product.attributes.custom_options) {
        product.attributes.custom_options.forEach((customOption, optIndex) => {
            const inputType = customOption.type === 'checkbox' ? 'checkbox' : 'radio';
            const inputName = inputType === 'radio' ? `custom_option_${optIndex}` : '';

            attributesHtml.push(`
        <div class="space-y-3">
            <div class="flex items-center gap-2">
                <h3 class="text-xs font-bold text-stone-800 dark:text-stone-200 uppercase tracking-[0.15em]">${customOption.title}</h3>
                ${customOption.required ? '<span class="text-xs text-red-500 font-medium">* Obligatoire</span>' : ''}
            </div>
            <div class="space-y-2">
                ${customOption.items.map((item, itemIndex) => {
                    const isChecked = (inputType === 'radio' && itemIndex === 0) ? 'checked' : '';
                    if (isChecked) {
                        selectedOptions.push({
                            name: item.name,
                            price: parseFloat(item.price || 0),
                            optionIndex: optIndex
                        });
                    }
                    return `
                    <label class="flex items-center justify-between p-3.5 bg-stone-50 dark:bg-white/5 border border-stone-200 dark:border-white/10 rounded-2xl cursor-pointer hover:border-primary-500 hover:bg-primary-50/50 smooth-transition">
                        <div class="flex items-center gap-3">
                            <input type="${inputType}" 
                                   ${inputName ? `name="${inputName}"` : ''}
                                   value="${item.name}" 
                                   data-price="${item.price}"
                                   data-option-index="${optIndex}"
                                   onchange="handleCustomOptionChange(this, '${inputType}', ${optIndex}, ${customOption.required})"
                                   class="w-4 h-4 ${inputType === 'radio' ? '' : 'rounded'}"
                                   ${isChecked}>
                            <span class="text-gray-700 dark:text-stone-200 text-sm font-medium">${item.name}</span>
                        </div>
                        <span class="${item.price > 0 ? 'text-primary-500' : 'text-green-600'} text-sm font-semibold">
                            ${item.price > 0 ? '+' + item.price.toLocaleString('fr-FR') + ' F' : 'Inclus'}
                        </span>
                    </label>
                `;
                }).join('')}
            </div>
        </div>
    `);
        });
    }

    // Supplements
    if (product.attributes && product.attributes.supplements) {
        attributesHtml.push(`
    <div class="space-y-3">
        <h3 class="text-xs font-bold text-stone-800 dark:text-stone-200 uppercase tracking-[0.15em]">Suppléments</h3>
        <div class="space-y-2">
            ${product.attributes.supplements.map(opt => `
                <label class="flex items-center justify-between p-3.5 bg-stone-50 dark:bg-white/5 border border-stone-200 dark:border-white/10 rounded-2xl cursor-pointer hover:border-primary-500 hover:bg-primary-50/50 smooth-transition">
                    <div class="flex items-center gap-3">
                        <input type="checkbox" value="${opt.name}" data-price="${opt.price}" 
                               onchange="handleOptionChange(this, 'supplements')"
                               class="w-4 h-4 rounded">
                        <span class="text-gray-700 dark:text-stone-200 text-sm font-medium">${opt.name}</span>
                    </div>
                    <span class="text-primary-500 text-sm font-semibold">+${parseFloat(opt.price).toLocaleString('fr-FR')} F</span>
                </label>
            `).join('')}
        </div>
    </div>
`);
    }

    // Garnitures
    if (product.attributes && product.attributes.garnitures) {
        attributesHtml.push(`
    <div class="space-y-3">
        <div class="flex items-center gap-2">
            <h3 class="text-xs font-bold text-stone-800 dark:text-stone-200 uppercase tracking-[0.15em]">Garnitures</h3>
            <span class="text-xs text-red-500 font-medium">* Obligatoire</span>
        </div>
        <div class="space-y-2">
            ${product.attributes.garnitures.map(opt => `
                <label class="flex items-center justify-between p-3.5 bg-stone-50 dark:bg-white/5 border border-stone-200 dark:border-white/10 rounded-2xl cursor-pointer hover:border-primary-500 hover:bg-primary-50/50 smooth-transition">
                    <div class="flex items-center gap-3">
                        <input type="radio" name="garniture" value="${opt}" 
                               onchange="handleOptionChange(this, 'garnitures')"
                               class="w-4 h-4">
                        <span class="text-gray-700 dark:text-stone-200 text-sm font-medium">${opt}</span>
                    </div>
                    <span class="text-green-600 text-xs font-semibold">Inclus</span>
                </label>
            `).join('')}
        </div>
    </div>
`);
    }

    // Parfums
    if (product.attributes && product.attributes.parfums) {
        attributesHtml.push(`
    <div class="space-y-3">
        <div class="flex items-center gap-2">
            <h3 class="text-xs font-bold text-stone-800 dark:text-stone-200 uppercase tracking-[0.15em]">Parfums de glace</h3>
            <span class="text-xs text-red-500 font-medium">* Obligatoire</span>
        </div>
        <div class="grid grid-cols-2 gap-2">
            ${product.attributes.parfums.map(parfum => `
                <label class="flex items-center gap-2 p-3 bg-stone-50 dark:bg-white/5 border border-stone-200 dark:border-white/10 rounded-2xl cursor-pointer hover:border-primary-500 hover:bg-primary-50/50 smooth-transition">
                    <input type="checkbox" value="${parfum}" onchange="handleParfumChange(this)"
                           class="w-4 h-4 rounded">
                    <span class="text-sm text-gray-700 dark:text-stone-200">${parfum}</span>
                </label>
            `).join('')}
        </div>
    </div>
`);
    }

    document.getElementById('modalAttributes').innerHTML = attributesHtml.join('');
    document.getElementById('validationError').classList.add('hidden');
    updateModalPrice();
    document.getElementById('productModal').classList.remove('hidden');
    if (window.lucide) lucide.createIcons();
}

function closeModal(event) {
    if (event.target.classList.contains('fixed')) {
        closeProductModal();
        closeCartModal();
    }
}

function closeProductModal() {
    document.getElementById('productModal').classList.add('hidden');
}

function closeCartModal() {
    document.getElementById('cartModal').classList.add('hidden');
}

function handleOptionChange(input, type) {
    const option = {
        name: input.value,
        price: parseInt(input.dataset.price || 0)
    };

    if (type === 'supplements') {
        if (input.checked) {
            selectedOptions.push(option);
        } else {
            selectedOptions = selectedOptions.filter(o => o.name !== option.name);
        }
    } else {
        // Garnitures -> Radio behavior but logically filter
        // If garniture logic is strictly one per dish, we remove previous garnitures
        const allGarnitures = supplementsData.garnitures || [];
        selectedOptions = selectedOptions.filter(o => !allGarnitures.includes(o.name));
        selectedOptions.push(option);
        document.getElementById('validationError').classList.add('hidden');
    }

    updateModalPrice();
}

function handleManualVariationChange(input) {
    if (input.checked) {
        const price = parseFloat(input.dataset.price);
        const name = input.dataset.name;

        currentProduct.price = price;
        currentProduct.selectedVariation = {
            name: name,
            price: price,
            isManual: true
        };

        updateModalPrice();
    }
}

function handleParfumChange(input) {
    const parfum = { name: input.value, price: 1000 };

    if (input.checked) {
        selectedParfums.push(parfum);
    } else {
        selectedParfums = selectedParfums.filter(p => p.name !== parfum.name);
    }

    document.getElementById('validationError').classList.add('hidden');
    updateModalPrice();
}

function handleCustomOptionChange(input, type, optionIndex, required) {
    const option = {
        name: input.value,
        price: parseFloat(input.dataset.price || 0),
        optionIndex: optionIndex
    };

    if (type === 'radio') {
        selectedOptions = selectedOptions.filter(o => o.optionIndex !== optionIndex);
        if (input.checked) selectedOptions.push(option);
    } else {
        if (input.checked) selectedOptions.push(option);
        else selectedOptions = selectedOptions.filter(o => !(o.name === option.name && o.optionIndex === optionIndex));
    }

    document.getElementById('validationError').classList.add('hidden');
    updateModalPrice();
}

function updateModalPrice() {
    if (!currentProduct) return;

    const basePrice = parseFloat(currentProduct.price);
    const optionsPrice = selectedOptions.reduce((sum, opt) => sum + opt.price, 0);
    const parfumsPrice = selectedParfums.length > 1 ? (selectedParfums.length - 1) * 1000 : 0;
    const totalPrice = basePrice + optionsPrice + parfumsPrice;

    document.getElementById('modalTotalPrice').textContent = `${totalPrice.toLocaleString('fr-FR')} F`;
}

function addToCart() {
    if (!currentProduct) return;

    // Validation Variations
    if (currentProduct.is_variable && !currentProduct.selectedVariation) {
        showError('Veuillez choisir une option.');
        return;
    }

    // Validation Custom Options
    if (currentProduct.attributes && currentProduct.attributes.custom_options) {
        for (let i = 0; i < currentProduct.attributes.custom_options.length; i++) {
            const customOpt = currentProduct.attributes.custom_options[i];
            if (customOpt.required) {
                const hasSelection = selectedOptions.some(opt => opt.optionIndex === i);
                if (!hasSelection) {
                    showError(`Veuillez sélectionner une option pour "${customOpt.title}".`);
                    return;
                }
            }
        }
    }

    // Validation Garnitures
    if (currentProduct.attributes && currentProduct.attributes.garnitures) {
        const hasGarniture = selectedOptions.some(opt => supplementsData.garnitures.includes(opt.name));
        if (!hasGarniture) {
            showError('Veuillez choisir une garniture.');
            return;
        }
    }

    // Validation Parfums
    if (currentProduct.attributes && currentProduct.attributes.parfums && selectedParfums.length === 0) {
        showError('Veuillez sélectionner au moins un parfum de glace.');
        return;
    }

    const basePrice = parseFloat(currentProduct.price);
    const optionsPrice = selectedOptions.reduce((sum, opt) => sum + opt.price, 0);
    const parfumsPrice = selectedParfums.length > 1 ? (selectedParfums.length - 1) * 1000 : 0;
    const itemPrice = basePrice + optionsPrice + parfumsPrice;

    let productName = currentProduct.name;
    if (currentProduct.selectedVariation) {
        productName += ` (${currentProduct.selectedVariation.name})`;
    }

    const cartItem = {
        id: currentProduct.id,                // dish id (for stock tracking)
        uniqueId: Date.now(),                  // unique cart slot id
        name: productName,
        price: basePrice,
        itemPrice: itemPrice,
        quantity: 1,
        selectedOptions: [...selectedOptions, ...selectedParfums]
    };

    cart.push(cartItem);
    localStorage.setItem('restaurantCart', JSON.stringify(cart));

    updateCartBadge();
    closeProductModal();
}

function showError(msg) {
    const err = document.getElementById('validationError');
    err.textContent = msg;
    err.classList.remove('hidden');
}

function showCart() {
    const container = document.getElementById('cartItems');
    if (cart.length === 0) {
        container.innerHTML = '<div class="text-center py-14 text-stone-400 dark:text-stone-500"><p class="font-serif-d text-2xl text-stone-500 dark:text-stone-400 mb-1">Panier vide</p><p class="text-sm">Ajoutez vos plats préférés depuis le menu.</p></div>';
    } else {
        renderCartItems();
    }
    document.getElementById('cartModal').classList.remove('hidden');
}

// Frais de service répercutés au client : 10%
const SERVICE_FEE_RATE = 0.10;

function calcFee(subtotal) {
    return Math.round(subtotal * SERVICE_FEE_RATE);
}

function renderCartItems() {
    const subtotal = cart.reduce((sum, item) => sum + (item.itemPrice * item.quantity), 0);
    const serviceFee = calcFee(subtotal);
    const total = subtotal + serviceFee;
    document.getElementById('cartItems').innerHTML = `
        <div class="space-y-3">
            ${cart.map((item, index) => `
                <div class="bg-white dark:bg-white/5 rounded-2xl p-4 border border-stone-200 dark:border-white/10">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex-1 min-w-0">
                            <h3 class="font-serif-d font-semibold text-stone-900 dark:text-white leading-snug">${item.name}</h3>
                            <div class="text-xs text-stone-500 dark:text-stone-400 mt-1 space-y-0.5">
                                ${item.selectedOptions.map(o => `<div>+ ${typeof o === 'object' ? o.name : o}</div>`).join('')}
                            </div>
                        </div>
                        <button onclick="removeFromCart(${index})"
                                aria-label="Retirer"
                                class="flex-shrink-0 w-7 h-7 flex items-center justify-center rounded-full bg-red-50 text-red-500 hover:bg-red-100 transition text-sm font-bold">✕</button>
                    </div>
                    <div class="flex items-center justify-between mt-3">
                        <div class="flex items-center gap-2 bg-stone-100 dark:bg-white/10 rounded-full p-1">
                            <button onclick="updateCartQuantity(${index}, -1)"
                                    class="w-8 h-8 flex items-center justify-center rounded-full bg-white dark:bg-white/10 text-stone-700 dark:text-stone-200 hover:text-primary-600 font-bold text-lg transition shadow-sm">
                                −
                            </button>
                            <span class="w-6 text-center font-bold text-stone-900 dark:text-white text-sm">${item.quantity}</span>
                            <button onclick="updateCartQuantity(${index}, 1)"
                                    class="w-8 h-8 flex items-center justify-center rounded-full bg-white dark:bg-white/10 text-stone-700 dark:text-stone-200 hover:text-primary-600 font-bold text-lg transition shadow-sm">
                                +
                            </button>
                        </div>
                        <span class="font-serif-d font-bold text-stone-900 dark:text-white text-lg">${(item.itemPrice * item.quantity).toLocaleString('fr-FR')} F</span>
                    </div>
                </div>
            `).join('')}
            <div class="bg-ink-950 rounded-2xl p-5 text-white mt-2 space-y-2">
                <div class="flex justify-between items-center text-sm text-stone-300">
                    <span>Sous-total</span>
                    <span>${subtotal.toLocaleString('fr-FR')} F</span>
                </div>
                <div class="flex justify-between items-center text-sm text-stone-300">
                    <span>Frais de service (10%)</span>
                    <span>${serviceFee.toLocaleString('fr-FR')} F</span>
                </div>
                <p class="text-[11px] text-stone-500">Payin + retrait + service inclus · Hors livraison (dès 1 000 F, calculée à l'étape suivante)</p>
                <div class="flex justify-between items-center pt-2 border-t border-white/10">
                    <span class="font-serif-d text-lg">Total</span>
                    <span class="font-serif-d text-2xl font-bold text-primary-500">${total.toLocaleString('fr-FR')} F</span>
                </div>
            </div>
            <button onclick="window.location.href=(typeof CHECKOUT_URL !== 'undefined' ? CHECKOUT_URL : 'checkout')"
                    class="w-full py-4 bg-primary-500 text-white rounded-2xl font-bold hover:bg-primary-600 smooth-transition shadow-lg shadow-primary-500/25 mt-2 flex items-center justify-center gap-2">
                Valider la commande <span aria-hidden="true">→</span>
            </button>
        </div>
    `;
}

function updateCartQuantity(index, delta) {
    if (!cart[index]) return;
    cart[index].quantity = Math.max(1, cart[index].quantity + delta);
    localStorage.setItem('restaurantCart', JSON.stringify(cart));
    renderCartItems();
    updateCartBadge();
}

function removeFromCart(index) {
    cart.splice(index, 1);
    localStorage.setItem('restaurantCart', JSON.stringify(cart));
    if (cart.length === 0) {
        document.getElementById('cartItems').innerHTML = '<div class="text-center py-14 text-stone-400 dark:text-stone-500"><p class="font-serif-d text-2xl text-stone-500 dark:text-stone-400 mb-1">Panier vide</p><p class="text-sm">Ajoutez vos plats préférés depuis le menu.</p></div>';
    } else {
        renderCartItems();
    }
    updateCartBadge();
}

function updateCartBadge() {
    const count = cart.reduce((sum, item) => sum + item.quantity, 0);
    const badge = document.getElementById('cartBadge');
    if (badge) badge.textContent = count;
    const btn = document.getElementById('floatingCartBtn');
    if (btn) {
        if (count > 0) btn.classList.remove('hidden');
        else btn.classList.add('hidden');
    }
    const totalEl = document.getElementById('floatingCartTotal');
    if (totalEl) {
        const subtotal = cart.reduce((sum, item) => sum + (item.itemPrice * item.quantity), 0);
        totalEl.textContent = (subtotal + calcFee(subtotal)).toLocaleString('fr-FR') + ' F';
    }
    if (window.lucide) lucide.createIcons();
}
