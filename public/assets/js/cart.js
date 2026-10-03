// Global State
let currentMenu = 'plats';
let currentProduct = null;
let cart = JSON.parse(localStorage.getItem('restaurantCart')) || [];
let selectedOptions = [];
let selectedParfums = [];

document.addEventListener('DOMContentLoaded', function () {
    // Initial Render
    updateCartBadge();

    // URL Params
    const urlParams = new URLSearchParams(window.location.search);
    const menuParam = urlParams.get('menu');
    if (menuParam === 'plats' || menuParam === 'bar') {
        selectMenu(menuParam);
    }
});

function showHome() {
    document.getElementById('homePage').classList.remove('hidden');
    document.getElementById('menuPage').classList.add('hidden');
}

function selectMenu(menu) {
    currentMenu = menu;
    document.getElementById('homePage').classList.add('hidden');
    document.getElementById('menuPage').classList.remove('hidden');
    document.getElementById('menuTitle').textContent = menu === 'plats' ? 'Menu Plats' : 'Menu Bar';
    renderMenu();
}

function toggleCategoryNav() {
    const nav = document.getElementById('categoryNav');
    const overlay = document.getElementById('categoryOverlay');

    if (nav.classList.contains('translate-x-0')) {
        nav.classList.remove('translate-x-0');
        nav.classList.add('translate-x-full');
        overlay.classList.add('hidden');
    } else {
        nav.classList.remove('translate-x-full');
        nav.classList.add('translate-x-0');
        overlay.classList.remove('hidden');
    }
}

function scrollToCategory(categoryId) {
    document.getElementById(categoryId)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    toggleCategoryNav();
}

function renderMenu() {
    const filteredProducts = productsData.filter(p => p.menu === currentMenu);
    const categories = [...new Set(filteredProducts.map(p => p.category))];

    // Desktop Category List
    const categoryList = document.getElementById('categoryList');
    if (categoryList) {
        categoryList.innerHTML = categories.map(cat => `
            <button onclick="scrollToCategory('${cat.replace(/[^a-zA-Z0-9]/g, '')}')" 
                    class="w-full text-left px-4 py-3 text-gray-700 hover:bg-gray-100 rounded-lg smooth-transition font-medium text-sm">
                ${cat}
            </button>
        `).join('');
    }

    // Mobile Bottom Category Menu
    const mobileCategoryNav = document.getElementById('mobileCategoryNav');
    if (mobileCategoryNav) {
        mobileCategoryNav.innerHTML = categories.map(cat => `
            <button onclick="scrollToCategory('${cat.replace(/[^a-zA-Z0-9]/g, '')}')" 
                    class="whitespace-nowrap px-4 py-2 bg-gray-100 text-gray-700 rounded-full text-xs font-semibold hover:bg-primary-500 hover:text-white smooth-transition">
                ${cat}
            </button>
        `).join('');
    }

    const menuGrid = document.getElementById('menuGrid');
    menuGrid.innerHTML = categories.map(category => {
        const categoryProducts = filteredProducts.filter(p => p.category === category);
        return `
    <section id="${category.replace(/[^a-zA-Z0-9]/g, '')}">
        <div class="mb-6">
            <h2 class="text-2xl font-semibold text-gray-900 mb-1">${category}</h2>
            <div class="w-12 h-1 bg-primary-500 rounded-full"></div>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
            ${categoryProducts.map(p => {
            // Escape single quotes for JSON
            const pJson = JSON.stringify(p).replace(/'/g, "&#39;");
            return `
                <button onclick='showProductModal(${pJson})' 
                        class="text-left bg-white rounded-xl p-5 card-hover border border-gray-200 hover:border-gray-300 overflow-hidden w-full">
                    ${p.image ? `
                        <div class="w-full h-40 mb-4 rounded-lg overflow-hidden bg-gray-100">
                            <img src="${p.image}" 
                                 alt="${p.name}" 
                                 class="w-full h-full object-cover"
                                 loading="lazy"
                                 onerror="this.parentElement.style.display='none'">
                        </div>
                    ` : ''}
                    <h3 class="font-semibold text-gray-900 mb-2 text-lg">${p.name}</h3>
                    <p class="text-gray-500 text-sm mb-4 line-clamp-2 min-h-[2.5rem]">${p.description || ''}</p>
                    <div class="flex items-center justify-between pt-3 border-t border-gray-100">
                        <span class="text-xl font-bold text-gray-900">
                            ${p.attributes && p.attributes.manual_variations
                    ? (() => {
                        const prices = p.attributes.manual_variations.items.map(i => i.price).sort((a, b) => a - b);
                        return `${prices[0].toLocaleString('fr-FR')} F`;
                    })()
                    : parseFloat(p.price).toLocaleString('fr-FR') + ' F'
                }
                        </span>
                        <span class="text-primary-500 text-sm font-medium">Voir ÔåÆ</span>
                    </div>
                </button>
            `}).join('')}
        </div>
    </section>
`;
    }).join('');

    updateCartBadge();
}

function showProductModal(product) {
    currentProduct = JSON.parse(JSON.stringify(product));
    selectedOptions = [];
    selectedParfums = [];

    // Poissons logic: Check if variations exist
    if (product.category === 'Poissons' && (!product.attributes || !product.attributes.manual_variations || product.attributes.manual_variations.items.length === 0)) {
        alert("D├®sol├®, aucun poisson n'est disponible pour le moment.");
        return;
    }

    document.getElementById('modalProductName').textContent = product.name;
    document.getElementById('modalProductDescription').textContent = product.description || '';

    const attributesHtml = [];

    // Image
    if (product.image && product.image !== '') {
        attributesHtml.push(`
    <div class="mb-6">
        <div class="relative rounded-xl overflow-hidden bg-gray-100">
            <img src="${product.image}" 
                 alt="${product.name}" 
                 class="w-full h-64 object-cover"
                 onerror="this.parentElement.parentElement.style.display='none'">
        </div>
    </div>
`);
    }

    // Manual Variations
    if (product.attributes && product.attributes.manual_variations) {
        const variations = product.attributes.manual_variations;
        attributesHtml.push(`
    <div class="space-y-3">
        <div class="flex items-center gap-2">
            <h3 class="text-sm font-semibold text-gray-900 uppercase tracking-wide">${variations.title}</h3>
            <span class="text-xs text-red-500 font-medium">* Obligatoire</span>
        </div>
        <div class="space-y-2">
            ${variations.items.map((item, index) => `
                <label class="flex items-center justify-between p-3 bg-gray-50 rounded-lg cursor-pointer hover:bg-gray-100 smooth-transition">
                    <div class="flex items-center gap-3">
                        <input type="radio" 
                               name="manual_variation" 
                               value="${index}" 
                               data-price="${item.price}"
                               data-name="${item.name}"
                               ${index === 0 ? 'checked' : ''}
                               onchange="handleManualVariationChange(this)"
                               class="w-4 h-4">
                        <span class="text-gray-700 text-sm font-medium">${item.name}</span>
                    </div>
                    <span class="text-gray-900 font-bold">${parseFloat(item.price).toLocaleString('fr-FR')} F</span>
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
                <h3 class="text-sm font-semibold text-gray-900 uppercase tracking-wide">${customOption.title}</h3>
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
                    <label class="flex items-center justify-between p-3 bg-gray-50 rounded-lg cursor-pointer hover:bg-gray-100 smooth-transition">
                        <div class="flex items-center gap-3">
                            <input type="${inputType}" 
                                   ${inputName ? `name="${inputName}"` : ''}
                                   value="${item.name}" 
                                   data-price="${item.price}"
                                   data-option-index="${optIndex}"
                                   onchange="handleCustomOptionChange(this, '${inputType}', ${optIndex}, ${customOption.required})"
                                   class="w-4 h-4 ${inputType === 'radio' ? '' : 'rounded'}"
                                   ${isChecked}>
                            <span class="text-gray-700 text-sm font-medium">${item.name}</span>
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
        <h3 class="text-sm font-semibold text-gray-900 uppercase tracking-wide">Suppl├®ments</h3>
        <div class="space-y-2">
            ${product.attributes.supplements.map(opt => `
                <label class="flex items-center justify-between p-3 bg-gray-50 rounded-lg cursor-pointer hover:bg-gray-100 smooth-transition">
                    <div class="flex items-center gap-3">
                        <input type="checkbox" value="${opt.name}" data-price="${opt.price}" 
                               onchange="handleOptionChange(this, 'supplements')"
                               class="w-4 h-4 rounded">
                        <span class="text-gray-700 text-sm font-medium">${opt.name}</span>
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
            <h3 class="text-sm font-semibold text-gray-900 uppercase tracking-wide">Garnitures</h3>
            <span class="text-xs text-red-500 font-medium">* Obligatoire</span>
        </div>
        <div class="space-y-2">
            ${product.attributes.garnitures.map(opt => `
                <label class="flex items-center justify-between p-3 bg-gray-50 rounded-lg cursor-pointer hover:bg-gray-100 smooth-transition">
                    <div class="flex items-center gap-3">
                        <input type="radio" name="garniture" value="${opt}" 
                               onchange="handleOptionChange(this, 'garnitures')"
                               class="w-4 h-4">
                        <span class="text-gray-700 text-sm font-medium">${opt}</span>
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
            <h3 class="text-sm font-semibold text-gray-900 uppercase tracking-wide">Parfums de glace</h3>
            <span class="text-xs text-red-500 font-medium">* Obligatoire</span>
        </div>
        <div class="grid grid-cols-2 gap-2">
            ${product.attributes.parfums.map(parfum => `
                <label class="flex items-center gap-2 p-2.5 bg-gray-50 rounded-lg cursor-pointer hover:bg-gray-100 smooth-transition">
                    <input type="checkbox" value="${parfum}" onchange="handleParfumChange(this)"
                           class="w-4 h-4 rounded">
                    <span class="text-sm text-gray-700">${parfum}</span>
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
                    showError(`Veuillez s├®lectionner une option pour "${customOpt.title}".`);
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
        showError('Veuillez s├®lectionner au moins un parfum de glace.');
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
        container.innerHTML = '<p class="text-center text-gray-500 py-10">Votre panier est vide.</p>';
    } else {
        renderCartItems();
    }
    document.getElementById('cartModal').classList.remove('hidden');
}

// Frais de service r├®percut├®s au client : 10%
const SERVICE_FEE_RATE = 0.10;

function calcFee(subtotal) {
    return Math.round(subtotal * SERVICE_FEE_RATE);
}

function renderCartItems() {
    const subtotal = cart.reduce((sum, item) => sum + (item.itemPrice * item.quantity), 0);
    const serviceFee = calcFee(subtotal);
    const total = subtotal + serviceFee;
    document.getElementById('cartItems').innerHTML = `
        <div class="space-y-4">
            ${cart.map((item, index) => `
                <div class="bg-gray-50 rounded-xl p-4 border border-gray-200">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex-1 min-w-0">
                            <h3 class="font-semibold text-gray-900 text-sm leading-snug">${item.name}</h3>
                            <div class="text-xs text-gray-500 mt-1 space-y-0.5">
                                ${item.selectedOptions.map(o => `<div>+ ${typeof o === 'object' ? o.name : o}</div>`).join('')}
                            </div>
                        </div>
                        <button onclick="removeFromCart(${index})" 
                                class="flex-shrink-0 w-6 h-6 flex items-center justify-center rounded-full bg-red-100 text-red-500 hover:bg-red-200 transition text-xs font-bold">Ô£ò</button>
                    </div>
                    <div class="flex items-center justify-between mt-3">
                        <!-- Quantity Controls -->
                        <div class="flex items-center gap-2">
                            <button onclick="updateCartQuantity(${index}, -1)"
                                    class="w-8 h-8 flex items-center justify-center rounded-full border-2 border-gray-300 text-gray-700 hover:border-primary-500 hover:text-primary-500 font-bold text-lg transition">
                                ÔêÆ
                            </button>
                            <span class="w-6 text-center font-semibold text-gray-900 text-sm">${item.quantity}</span>
                            <button onclick="updateCartQuantity(${index}, 1)"
                                    class="w-8 h-8 flex items-center justify-center rounded-full border-2 border-gray-300 text-gray-700 hover:border-primary-500 hover:text-primary-500 font-bold text-lg transition">
                                +
                            </button>
                        </div>
                        <span class="font-bold text-gray-900">${(item.itemPrice * item.quantity).toLocaleString('fr-FR')} F</span>
                    </div>
                </div>
            `).join('')}
            <div class="bg-gray-900 rounded-xl p-5 text-white mt-4 space-y-2">
                <div class="flex justify-between items-center text-sm text-gray-300">
                    <span>Sous-total</span>
                    <span>${subtotal.toLocaleString('fr-FR')} F</span>
                </div>
                <div class="flex justify-between items-center text-sm text-gray-300">
                    <span>Frais de service (10%)</span>
                    <span>${serviceFee.toLocaleString('fr-FR')} F</span>
                </div>
                <p class="text-[11px] text-gray-400">Payin + retrait + service inclus ┬À Hors livraison (d├¿s 1 000 F, calcul├®e ├á l'├®tape suivante)</p>
                <div class="flex justify-between items-center pt-2 border-t border-gray-700">
                    <span class="text-base">Total</span>
                    <span class="text-2xl font-bold">${total.toLocaleString('fr-FR')} F</span>
                </div>
            </div>
            <button onclick="window.location.href=(typeof CHECKOUT_URL !== 'undefined' ? CHECKOUT_URL : 'checkout')" 
                    class="w-full py-4 bg-primary-500 text-white rounded-xl font-semibold hover:bg-primary-600 transition mt-2">Valider la commande ÔåÆ</button>
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
        document.getElementById('cartItems').innerHTML = '<p class="text-center text-gray-500 py-10">Votre panier est vide.</p>';
    } else {
        renderCartItems();
    }
    updateCartBadge();
}

function updateCartBadge() {
    const total = cart.reduce((sum, item) => sum + item.quantity, 0);
    const badge = document.getElementById('cartBadge');
    if (badge) badge.textContent = total;
    const btn = document.getElementById('floatingCartBtn');
    if (btn) {
        if (total > 0) btn.classList.remove('hidden');
        else btn.classList.add('hidden');
    }
}
