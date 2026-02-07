// js/map-modal.js
// MarketConnect - Map Modal Functionality

// ========================================
// 🎯 MAP CONFIGURATION - ADJUST HERE
// ========================================
const MODAL_MAP_CENTER_LAT = 6.218658;      // Polomolok Public Market Latitude
const MODAL_MAP_CENTER_LNG = 125.062795;    // Polomolok Public Market Longitude
const MODAL_MAP_INITIAL_ZOOM = 15;          // Initial zoom level (10-19)
const MODAL_WATERMARK_HIDE_ZOOM = 16;       // Hide watermark when zoom >= this level

// Zoom Level Guide:
// 13 = City-wide view (entire Polomolok)
// 14 = District view (multiple barangays)
// 15 = Neighborhood view (Barangay Poblacion) ✅ DEFAULT
// 16 = Street-level view (detailed)
// 17 = Building-level view (very detailed)
// ========================================

let modalMap = null;
let modalMarkers = [];
let currentVendorData = null;

// Open the map modal
function openMapModal() {
    const modal = document.getElementById('mapModal');
    modal.classList.remove('hidden');
    
    // Initialize modal map if not already done
    setTimeout(() => {
        if (!modalMap) {
            initializeModalMap();
        } else {
            modalMap.invalidateSize();
        }
    }, 100);
}

// Close the map modal
function closeMapModal() {
    const modal = document.getElementById('mapModal');
    modal.classList.add('hidden');
    closeStoreSidebar();
}

// Initialize the modal map
function initializeModalMap() {
    // Create map centered on Polomolok Public Market, Barangay Poblacion
    modalMap = L.map('modalMap').setView([MODAL_MAP_CENTER_LAT, MODAL_MAP_CENTER_LNG], MODAL_MAP_INITIAL_ZOOM);
    
    // Add tile layer
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors',
        maxZoom: 19
    }).addTo(modalMap);
    
    // Setup watermark visibility control
    const modalWatermark = document.getElementById('modalMapWatermark');
    
    function updateModalWatermarkVisibility() {
        const currentZoom = modalMap.getZoom();
        if (currentZoom >= MODAL_WATERMARK_HIDE_ZOOM) {
            modalWatermark.classList.add('hidden');
        } else {
            modalWatermark.classList.remove('hidden');
        }
    }
    
    // Listen for zoom changes
    modalMap.on('zoomend', updateModalWatermarkVisibility);
    
    // Initial check
    updateModalWatermarkVisibility();
    
    // Add vendor markers
    addModalMarkers();
}

// Add markers to modal map
function addModalMarkers() {
    // Clear existing markers
    modalMarkers.forEach(marker => modalMap.removeLayer(marker));
    modalMarkers = [];
    
    // Custom green marker icon
    const greenIcon = L.icon({
        iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-green.png',
        shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
        iconSize: [25, 41],
        iconAnchor: [12, 41],
        popupAnchor: [1, -34],
        shadowSize: [41, 41]
    });
    
    // Add markers for each vendor
    vendors.forEach(vendor => {
        if (vendor.location_lat && vendor.location_lng) {
            const marker = L.marker([vendor.location_lat, vendor.location_lng], {icon: greenIcon})
                .addTo(modalMap);
            
            // Create popup content
            const popupContent = `
                <div class="text-center p-3" style="min-width: 200px;">
                    <h3 class="font-bold text-lg mb-1">${vendor.business_name}</h3>
                    <p class="text-sm text-gray-600 mb-1">${vendor.market_name}</p>
                    ${vendor.stall_number ? `<p class="text-xs text-gray-500 mb-2">Stall ${vendor.stall_number}</p>` : ''}
                    <div class="mb-3">
                        <span class="text-yellow-500 font-semibold">⭐ ${vendor.rating_average}</span>
                        <span class="text-gray-500 text-xs">(${vendor.total_reviews} reviews)</span>
                    </div>
                    <button onclick="loadStoreDetails(${vendor.vendor_id})" 
                        class="bg-green-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-green-700 transition font-semibold w-full">
                        <i class="fas fa-eye"></i> View Details
                    </button>
                </div>
            `;
            
            marker.bindPopup(popupContent);
            
            // Store vendor data in marker for search
            marker.vendorData = vendor;
            modalMarkers.push(marker);
        }
    });
    
    // If no vendors, show message
    if (vendors.length === 0) {
        L.popup()
            .setLatLng([MODAL_MAP_CENTER_LAT, MODAL_MAP_CENTER_LNG])
            .setContent('<div class="text-center p-2"><strong class="text-lg">📍 No vendors with location data yet</strong><br><small class="text-gray-600">Vendors need to set their location</small></div>')
            .openOn(modalMap);
    }
}

// Modal search functionality
document.getElementById('modalSearchInput')?.addEventListener('input', function(e) {
    const query = e.target.value.toLowerCase().trim();
    
    if (!query) {
        // Show all markers
        modalMarkers.forEach(marker => {
            marker.setOpacity(1);
        });
        return;
    }
    
    // Filter markers based on search
    let foundCount = 0;
    let firstMatch = null;
    
    modalMarkers.forEach(marker => {
        const vendor = marker.vendorData;
        const businessName = vendor.business_name.toLowerCase();
        const category = (vendor.category || '').toLowerCase();
        const marketName = vendor.market_name.toLowerCase();
        
        if (businessName.includes(query) || category.includes(query) || marketName.includes(query)) {
            marker.setOpacity(1);
            foundCount++;
            if (!firstMatch) firstMatch = marker;
        } else {
            marker.setOpacity(0.3);
        }
    });
    
    // If found matches, zoom to first match
    if (firstMatch) {
        modalMap.setView(firstMatch.getLatLng(), 15);
        firstMatch.openPopup();
    }
});

// Locate user in modal map
function modalLocateMe() {
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(position => {
            const lat = position.coords.latitude;
            const lng = position.coords.longitude;
            modalMap.setView([lat, lng], 15);
            
            // Add user marker
            const blueIcon = L.icon({
                iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-blue.png',
                shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
                iconSize: [25, 41],
                iconAnchor: [12, 41],
                popupAnchor: [1, -34],
                shadowSize: [41, 41]
            });
            
            L.marker([lat, lng], {icon: blueIcon})
                .addTo(modalMap)
                .bindPopup('<strong>📍 You are here!</strong>')
                .openPopup();
        }, () => {
            alert('⚠️ Unable to retrieve your location. Please check your browser permissions.');
        });
    } else {
        alert('❌ Geolocation is not supported by your browser');
    }
}

// Load store details in sidebar
function loadStoreDetails(vendorId) {
    const sidebar = document.getElementById('storeSidebar');
    const storeTitle = document.getElementById('storeTitle');
    const storeDetails = document.getElementById('storeDetails');
    
    // Show sidebar with loading state
    sidebar.classList.remove('hidden');
    storeTitle.textContent = 'Loading...';
    storeDetails.innerHTML = '<div class="text-center py-8"><i class="fas fa-spinner fa-spin text-4xl text-green-600"></i><p class="mt-2 text-gray-600">Loading store details...</p></div>';
    
    // Fetch vendor details
    fetch(`api/get_vendor_details.php?vendor_id=${vendorId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayStoreDetails(data.vendor, data.products, data.photos);
            } else {
                storeDetails.innerHTML = '<div class="text-center py-8 text-red-600"><i class="fas fa-exclamation-circle text-4xl mb-2"></i><p>Failed to load store details</p></div>';
            }
        })
        .catch(error => {
            console.error('Error loading store details:', error);
            storeDetails.innerHTML = '<div class="text-center py-8 text-red-600"><i class="fas fa-exclamation-circle text-4xl mb-2"></i><p>Error loading store details</p></div>';
        });
}

// Display store details
function displayStoreDetails(vendor, products, photos) {
    const storeTitle = document.getElementById('storeTitle');
    const storeDetails = document.getElementById('storeDetails');
    
    storeTitle.textContent = vendor.business_name;
    
    let html = `
        <!-- Store Info -->
        <div class="bg-gradient-to-r from-green-500 to-green-600 text-white p-4 rounded-lg -mx-4 -mt-4 mb-4">
            <div class="flex items-center justify-between mb-2">
                <div>
                    <p class="text-sm opacity-90"><i class="fas fa-map-marker-alt"></i> ${vendor.market_name}</p>
                    ${vendor.stall_number ? `<p class="text-xs opacity-80">Stall ${vendor.stall_number}</p>` : ''}
                </div>
                <div class="text-right">
                    <div class="flex items-center text-yellow-300">
                        <i class="fas fa-star"></i>
                        <span class="ml-1 font-bold">${vendor.rating_average}</span>
                    </div>
                    <p class="text-xs opacity-80">${vendor.total_reviews} reviews</p>
                </div>
            </div>
        </div>
        
        ${vendor.description ? `
        <div class="mb-4">
            <h4 class="font-semibold text-gray-700 mb-2"><i class="fas fa-info-circle text-green-600"></i> About</h4>
            <p class="text-sm text-gray-600">${vendor.description}</p>
        </div>
        ` : ''}
        
        ${vendor.category ? `
        <div class="mb-4">
            <span class="inline-block bg-green-100 text-green-800 text-xs px-3 py-1 rounded-full font-semibold">
                <i class="fas fa-tag"></i> ${vendor.category}
            </span>
        </div>
        ` : ''}
    `;
    
    // Store Photos Section
    if (photos && photos.length > 0) {
        html += `
        <div class="mb-4">
            <h4 class="font-semibold text-gray-700 mb-3"><i class="fas fa-images text-green-600"></i> Store Photos (${photos.length})</h4>
            <div class="grid grid-cols-2 gap-2">
                ${photos.slice(0, 4).map(photo => `
                    <div class="relative aspect-square rounded-lg overflow-hidden cursor-pointer hover:opacity-90 transition"
                         onclick="viewImage('${photo.photo_url}')">
                        <img src="${photo.photo_url}" alt="${photo.caption || 'Store photo'}" 
                             class="w-full h-full object-cover">
                        ${photo.caption ? `
                        <div class="absolute bottom-0 left-0 right-0 bg-black bg-opacity-60 text-white text-xs p-1 text-center">
                            ${photo.caption}
                        </div>
                        ` : ''}
                    </div>
                `).join('')}
            </div>
            ${photos.length > 4 ? `
            <button onclick="viewAllPhotos(${vendor.vendor_id})" 
                class="mt-2 text-sm text-green-600 hover:text-green-800 font-semibold">
                <i class="fas fa-plus-circle"></i> View all ${photos.length} photos
            </button>
            ` : ''}
        </div>
        `;
    }
    
    // Products Section
    if (products && products.length > 0) {
        html += `
        <div class="mb-4">
            <h4 class="font-semibold text-gray-700 mb-3"><i class="fas fa-box text-green-600"></i> Products (${products.length})</h4>
            <div class="space-y-3 max-h-96 overflow-y-auto">
                ${products.map(product => `
                    <div class="border rounded-lg p-3 hover:shadow-md transition">
                        <div class="flex justify-between items-start">
                            <div class="flex-1">
                                <h5 class="font-semibold text-gray-800">${product.product_name}</h5>
                                ${product.description ? `
                                <p class="text-xs text-gray-600 mt-1">${product.description.substring(0, 80)}${product.description.length > 80 ? '...' : ''}</p>
                                ` : ''}
                                <div class="mt-2 flex items-center justify-between">
                                    <div>
                                        <span class="text-lg font-bold text-green-600">₱${parseFloat(product.price).toFixed(2)}</span>
                                        <span class="text-xs text-gray-500">/${product.unit}</span>
                                    </div>
                                    ${product.stock_quantity ? `
                                    <span class="text-xs ${product.stock_quantity < 10 ? 'text-red-600' : 'text-gray-600'}">
                                        <i class="fas fa-boxes"></i> ${product.stock_quantity} in stock
                                    </span>
                                    ` : ''}
                                </div>
                            </div>
                            ${product.image_url ? `
                            <img src="${product.image_url}" alt="${product.product_name}" 
                                 class="w-16 h-16 object-cover rounded ml-3">
                            ` : ''}
                        </div>
                        ${product.is_available ? `
                        <div class="mt-2">
                            <span class="inline-block bg-green-100 text-green-800 text-xs px-2 py-1 rounded">
                                <i class="fas fa-check-circle"></i> Available
                            </span>
                        </div>
                        ` : `
                        <div class="mt-2">
                            <span class="inline-block bg-red-100 text-red-800 text-xs px-2 py-1 rounded">
                                <i class="fas fa-times-circle"></i> Out of Stock
                            </span>
                        </div>
                        `}
                    </div>
                `).join('')}
            </div>
        </div>
        `;
    } else {
        html += `
        <div class="mb-4 text-center py-6 bg-gray-50 rounded-lg">
            <i class="fas fa-box-open text-gray-300 text-4xl mb-2"></i>
            <p class="text-gray-500">No products available yet</p>
        </div>
        `;
    }
    
    // Action Buttons
    html += `
    <div class="flex gap-2 mt-4 pt-4 border-t sticky bottom-0 bg-white">
        <a href="vendor_view.php?id=${vendor.vendor_id}" 
           class="flex-1 bg-green-600 text-white text-center py-3 rounded-lg hover:bg-green-700 transition font-semibold">
            <i class="fas fa-store"></i> Visit Store Page
        </a>
        <button onclick="contactVendor(${vendor.vendor_id})" 
           class="bg-blue-600 text-white px-4 py-3 rounded-lg hover:bg-blue-700 transition">
            <i class="fas fa-comment"></i>
        </button>
    </div>
    `;
    
    storeDetails.innerHTML = html;
}

// Close store sidebar
function closeStoreSidebar() {
    const sidebar = document.getElementById('storeSidebar');
    sidebar.classList.add('hidden');
}

// View image in lightbox (simple implementation)
function viewImage(imageUrl) {
    // Create a simple lightbox
    const lightbox = document.createElement('div');
    lightbox.className = 'fixed inset-0 bg-black bg-opacity-90 flex items-center justify-center z-[10001]';
    lightbox.innerHTML = `
        <div class="relative max-w-4xl max-h-screen p-4">
            <button onclick="this.parentElement.parentElement.remove()" 
                class="absolute top-4 right-4 text-white hover:text-gray-300 text-3xl">
                <i class="fas fa-times"></i>
            </button>
            <img src="${imageUrl}" class="max-w-full max-h-screen object-contain rounded-lg">
        </div>
    `;
    lightbox.onclick = function(e) {
        if (e.target === lightbox) {
            lightbox.remove();
        }
    };
    document.body.appendChild(lightbox);
}

// View all photos (navigate to vendor page)
function viewAllPhotos(vendorId) {
    window.location.href = `vendor_view.php?id=${vendorId}#photos`;
}

// Contact vendor
function contactVendor(vendorId) {
    window.location.href = `vendor_view.php?id=${vendorId}#contact`;
}

// Close modal on ESC key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeMapModal();
    }
});

// Close modal on outside click
document.getElementById('mapModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        closeMapModal();
    }
});