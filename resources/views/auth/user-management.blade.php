@extends('layouts.app')

@section('content')
@php
    $groups = $users->whereNotNull('group')->where('group', '!=', '')->groupBy('group')->map(function ($groupUsers,$groupName) {
        return (object)[
            'name' => $groupName,
            'active' => true,
            'users_count' => $groupUsers->count()
        ];
    })->values();

    $profiles = $users->whereNotNull('profile')->where('profile', '!=', '')->groupBy('profile')->map(function ($profileUsers,$profileName) {
        return (object)[
            'name' => $profileName,
            'active' => true,
            'users_count' => $profileUsers->count()
        ];
    })->values();
@endphp

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="mb-6">
        <h1 class="text-3xl font-bold text-[#101722] font-serif">Gestão de Acessos</h1>
        <p class="text-sm text-gray-500 mt-1">Gerencie os usuários, grupos e perfis do sistema.</p>
    </div>

    @if (session('status'))
        <div class="mb-6 p-4 bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm rounded-xl">
            {{ session('status') }}
        </div>
    @endif

    <div class="flex items-center space-x-3 mb-6">
        <button onclick="openAddModal()" class="inline-flex items-center space-x-2 px-4 py-2 border border-gray-900 rounded-lg text-sm font-medium text-gray-900 hover:bg-gray-50 transition">
            <i class="fa-solid fa-plus text-xs"></i>
            <span>Adicionar</span>
        </button>
    </div>

    <div class="border-b border-gray-200 mb-6 flex items-center justify-between">
        <div class="flex space-x-8">
            <button onclick="switchTab('users')" id="tabUsers" class="pb-3 border-b-2 border-gray-900 font-medium text-sm text-gray-900 transition">Usuários</button>
            <button onclick="switchTab('groups')" id="tabGroups" class="pb-3 text-sm text-gray-500 hover:text-gray-700 transition">Grupos</button>
            <button onclick="switchTab('profiles')" id="tabProfiles" class="pb-3 text-sm text-gray-500 hover:text-gray-700 transition">Perfis</button>
        </div>
        <button class="text-gray-500 hover:text-gray-900 pb-3 transition">
            <i class="fa-solid fa-rotate-right text-sm"></i>
        </button>
    </div>

    <div class="flex items-center space-x-3 mb-8">
        <div class="relative">
            <button id="filterButton" onclick="toggleFilterDropdown()" class="p-2.5 border border-gray-200 rounded-lg text-gray-500 hover:bg-gray-50 transition">
                <i class="fa-solid fa-filter text-sm"></i>
            </button>

            <div id="filterDropdown" class="hidden absolute left-0 mt-2 w-56 bg-white border border-gray-200 rounded-2xl shadow-xl z-50 p-2">
                <div class="px-3 py-2 text-[11px] font-semibold text-gray-400 tracking-wider uppercase">Situação</div>
                <button onclick="filterByStatus('all')" class="w-full flex items-center justify-between px-3 py-2 text-sm text-gray-700 hover:bg-gray-50 rounded-xl transition">
                    <span>Todos</span>
                    <i id="check-all" class="fa-solid fa-check text-xs text-gray-900"></i>
                </button>
                <button onclick="filterByStatus('ativo')" class="w-full flex items-center justify-between px-3 py-2 text-sm text-gray-700 hover:bg-gray-50 rounded-xl transition">
                    <span>Ativos</span>
                    <i id="check-ativo" class="hidden fa-solid fa-check text-xs text-gray-900"></i>
                </button>
                <button onclick="filterByStatus('inativo')" class="w-full flex items-center justify-between px-3 py-2 text-sm text-gray-700 hover:bg-gray-50 rounded-xl transition">
                    <span>Inativos</span>
                    <i id="check-inativo" class="hidden fa-solid fa-check text-xs text-gray-900"></i>
                </button>
            </div>
        </div>

        <div class="relative flex-1">
            <input type="text" id="searchInput" placeholder="Buscar por nome ou código..." class="w-full pl-4 pr-10 py-2.5 bg-white border border-gray-200 rounded-lg text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-1 focus:ring-gray-900 transition">
            <div class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400">
                <i class="fa-solid fa-magnifying-glass text-sm"></i>
            </div>
        </div>
    </div>

    <div id="sectionUsers" class="tab-section">
        <div id="usersGrid" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
            <?php foreach ($users as $user): ?>
            @php
                $isActive = $user->active ?? true; 
                $statusNormalized = $isActive ? 'ativo' : 'inativo';
            @endphp
            <div class="user-card bg-white border border-gray-200 rounded-2xl p-6 text-center flex flex-col justify-between" 
                 data-name="{{ strtolower($user->name) }}" 
                 data-username="{{ strtolower($user->username ?? '') }}"
                 data-status="{{ $statusNormalized }}">
                <div>
                    <!-- EXIBIÇÃO DA FOTO DO USUÁRIO -->
                    <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4 text-gray-600 overflow-hidden border border-gray-200">
                        @if(!empty($user->photo))
                            <img src="{{ asset('storage/' . $user->photo) }}" alt="Foto de {{ $user->name }}" class="w-full h-full object-cover">
                        @else
                            <i class="fa-solid fa-user text-xl"></i>
                        @endif
                    </div>

                    <h3 class="font-bold text-gray-900 text-base"><?php echo e($user->name); ?></h3>
                    <p class="text-xs text-gray-500 mt-0.5"><?php echo e($user->username ?? 'Sem identificador'); ?></p>
                    
                    @if($isActive)
                        <div class="inline-flex items-center space-x-1.5 px-2.5 py-0.5 rounded-full bg-emerald-50 text-emerald-700 text-xs font-medium mt-3">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                            <span>Ativo</span>
                        </div>
                    @else
                        <div class="inline-flex items-center space-x-1.5 px-2.5 py-0.5 rounded-full bg-rose-50 text-rose-700 text-xs font-medium mt-3">
                            <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>
                            <span>Inativo</span>
                        </div>
                    @endif
                </div>
                <button onclick="openEditModal(<?php echo htmlspecialchars(json_encode($user), ENT_QUOTES, 'UTF-8'); ?>)" class="w-full mt-6 py-2 border border-gray-200 rounded-lg text-xs font-medium text-gray-700 hover:bg-gray-50 transition flex items-center justify-center space-x-1.5">
                    <i class="fa-solid fa-pen text-[10px]"></i>
                    <span>Editar</span>
                </button>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div id="sectionGroups" class="tab-section hidden space-y-4 mb-8">
        <?php foreach ($groups as $group): ?>
        @php
            $isGroupActive = $group->active ?? true;
            $groupStatusNorm = $isGroupActive ? 'ativo' : 'inativo';
        @endphp
        <div class="group-card bg-white border border-gray-200 rounded-2xl p-5 flex items-center justify-between"
             data-name="{{ strtolower($group->name) }}"
             data-status="{{ $groupStatusNorm }}">
            <div class="flex items-center space-x-4">
                <div class="w-12 h-12 bg-gray-100 rounded-full flex items-center justify-center text-gray-600">
                    <i class="fa-solid fa-users text-base"></i>
                </div>
                <div>
                    <h3 class="font-bold text-gray-900 text-sm"><?php echo e($group->name); ?></h3>
                    <div class="flex items-center space-x-3 mt-1">
                        @if($isGroupActive)
                            <span class="inline-flex items-center space-x-1.5 px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 text-[11px] font-medium">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                <span>Ativo</span>
                            </span>
                        @else
                            <span class="inline-flex items-center space-x-1.5 px-2 py-0.5 rounded-full bg-rose-50 text-rose-700 text-[11px] font-medium">
                                <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>
                                <span>Inativo</span>
                            </span>
                        @endif
                        <span class="text-xs text-gray-500 flex items-center space-x-1">
                            <i class="fa-solid fa-user text-[10px]"></i>
                            <span>{{ $group->users_count }} usuários</span>
                        </span>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div id="sectionProfiles" class="tab-section hidden space-y-4 mb-8">
        <?php foreach ($profiles as $profile): ?>
        @php
            $isProfileActive = $profile->active ?? true;
            $profileStatusNorm = $isProfileActive ? 'ativo' : 'inativo';
        @endphp
        <div class="profile-card bg-white border border-gray-200 rounded-2xl p-5 flex items-center justify-between"
             data-name="{{ strtolower($profile->name) }}"
             data-status="{{ $profileStatusNorm }}">
            <div class="flex items-center space-x-4">
                <div class="w-12 h-12 bg-gray-100 rounded-full flex items-center justify-center text-gray-600">
                    <i class="fa-solid fa-shield-halved text-base"></i>
                </div>
                <div>
                    <h3 class="font-bold text-gray-900 text-sm"><?php echo e($profile->name); ?></h3>
                    <div class="flex items-center space-x-3 mt-1">
                        @if($isProfileActive)
                            <span class="inline-flex items-center space-x-1.5 px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 text-[11px] font-medium">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                <span>Ativo</span>
                            </span>
                        @else
                            <span class="inline-flex items-center space-x-1.5 px-2 py-0.5 rounded-full bg-rose-50 text-rose-700 text-[11px] font-medium">
                                <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>
                                <span>Inativo</span>
                            </span>
                        @endif
                        <span class="text-xs text-gray-500 flex items-center space-x-1">
                            <i class="fa-solid fa-user text-[10px]"></i>
                            <span>{{ $profile->users_count }} usuários</span>
                        </span>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="flex items-center justify-center space-x-2">
        <button class="p-1.5 text-gray-400 hover:text-gray-700 transition">
            <i class="fa-solid fa-chevron-left text-xs"></i>
        </button>
        <button class="w-8 h-8 rounded-full bg-gray-100 text-gray-900 font-medium text-xs flex items-center justify-center">1</button>
        <button class="p-1.5 text-gray-400 hover:text-gray-700 transition">
            <i class="fa-solid fa-chevron-right text-xs"></i>
        </button>
    </div>
</div>

@include('auth.partials.add-user-modal')
@include('auth.partials.edit-user-modal')

<script>
    let currentTab = 'users';
    let currentStatusFilter = 'all';

    function switchTab(tab) {
        currentTab = tab;
        
        ['users', 'groups', 'profiles'].forEach(t => {
            const btn = document.getElementById(`tab${t.charAt(0).toUpperCase() + t.slice(1)}`);
            const sec = document.getElementById(`section${t.charAt(0).toUpperCase() + t.slice(1)}`);
            if (t === tab) {
                btn.className = "pb-3 border-b-2 border-gray-900 font-medium text-sm text-gray-900 transition";
                sec.classList.remove('hidden');
            } else {
                btn.className = "pb-3 text-sm text-gray-500 hover:text-gray-700 transition";
                sec.classList.add('hidden');
            }
        });

        applyFilters();
    }

    function toggleFilterDropdown() {
        const dropdown = document.getElementById('filterDropdown');
        dropdown.classList.toggle('hidden');
    }

    window.addEventListener('click', function(e) {
        const btn = document.getElementById('filterButton');
        const dropdown = document.getElementById('filterDropdown');
        if (!btn.contains(e.target) && !dropdown.contains(e.target)) {
            dropdown.classList.add('hidden');
        }
    });

    function filterByStatus(status) {
        currentStatusFilter = status;

        ['all', 'ativo', 'inativo'].forEach(s => {
            const check = document.getElementById(`check-${s}`);
            if (s === status) {
                check.classList.remove('hidden');
            } else {
                check.classList.add('hidden');
            }
        });

        toggleFilterDropdown();
        applyFilters();
    }

    document.getElementById('searchInput').addEventListener('input', function() {
        applyFilters();
    });

    function applyFilters() {
        let filterText = document.getElementById('searchInput').value.toLowerCase();

        if (currentTab === 'users') {
            let cards = document.querySelectorAll('.user-card');
            cards.forEach(card => {
                let name = card.getAttribute('data-name');
                let username = card.getAttribute('data-username');
                let status = card.getAttribute('data-status');

                let matchesText = name.includes(filterText) || username.includes(filterText);
                let matchesStatus = (currentStatusFilter === 'all' || status === currentStatusFilter);

                if (matchesText && matchesStatus) {
                    card.style.display = "";
                } else {
                    card.style.display = "none";
                }
            });
        } else if (currentTab === 'groups') {
            let cards = document.querySelectorAll('.group-card');
            cards.forEach(card => {
                let name = card.getAttribute('data-name');
                let status = card.getAttribute('data-status');

                let matchesText = name.includes(filterText);
                let matchesStatus = (currentStatusFilter === 'all' || status === currentStatusFilter);

                if (matchesText && matchesStatus) {
                    card.style.display = "";
                } else {
                    card.style.display = "none";
                }
            });
        } else if (currentTab === 'profiles') {
            let cards = document.querySelectorAll('.profile-card');
            cards.forEach(card => {
                let name = card.getAttribute('data-name');
                let status = card.getAttribute('data-status');

                let matchesText = name.includes(filterText);
                let matchesStatus = (currentStatusFilter === 'all' || status === currentStatusFilter);

                if (matchesText && matchesStatus) {
                    card.style.display = "";
                } else {
                    card.style.display = "none";
                }
            });
        }
    }

    function openAddModal() {
        document.getElementById('addUserModal').classList.remove('hidden');
    }

    function closeAddModal() {
        document.getElementById('addUserModal').classList.add('hidden');
    }

    function toggleAddPasswordVisibility() {
        const input = document.getElementById('addPassword');
        const icon = document.getElementById('addPasswordEyeIcon');
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    }

    function openEditModal(user) {
        document.getElementById('modalName').value = user.name || '';
        document.getElementById('modalCode').value = String(user.id || '1').padStart(3, '0');
        document.getElementById('modalUnit').value = user.unit || '';
        document.getElementById('modalGroup').value = user.group || '';
        document.getElementById('modalProfile').value = user.profile || '';
        document.getElementById('modalPassword').value = '';
        document.getElementById('removePhotoFlag').value = '0';

        const preview = document.getElementById('editPreviewImage');
        const icon = document.getElementById('editDefaultUserIcon');
        const removeBtn = document.getElementById('editRemovePhotoButton');
        const input = document.getElementById('editPhotoInput');
        
        input.value = '';

        if (user.photo) {
            preview.src = `/storage/${user.photo}`;
            preview.classList.remove('hidden');
            icon.classList.add('hidden');
            removeBtn.classList.remove('hidden');
        } else {
            preview.src = '';
            preview.classList.add('hidden');
            icon.classList.remove('hidden');
            removeBtn.classList.add('hidden');
        }

        document.getElementById('editUserForm').action = `/usuarios/${user.id}`;
        document.getElementById('editUserModal').classList.remove('hidden');
    }

    function closeEditModal() {
        document.getElementById('editUserModal').classList.add('hidden');
    }

    function togglePasswordVisibility() {
        const passwordInput = document.getElementById('modalPassword');
        const eyeIcon = document.getElementById('passwordEyeIcon');

        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            eyeIcon.classList.remove('fa-eye');
            eyeIcon.classList.add('fa-eye-slash');
        } else {
            passwordInput.type = 'password';
            eyeIcon.classList.remove('fa-eye-slash');
            eyeIcon.classList.add('fa-eye');
        }
    }
</script>
@endsection