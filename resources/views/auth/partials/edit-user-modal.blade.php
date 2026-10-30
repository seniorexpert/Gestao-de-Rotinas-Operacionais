<div id="editUserModal" class="fixed inset-0 bg-black/40 z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl w-full max-w-lg overflow-hidden shadow-2xl border border-stone-200/60">
        <div class="px-8 pt-7 pb-5 flex items-center justify-between border-b border-stone-100">
            <h2 class="text-2xl font-bold text-[#1e2e3e] font-serif">Editar Usuário</h2>
            <button onclick="closeEditModal()" class="text-gray-400 hover:text-gray-600 transition">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>

        <form id="editUserForm" method="POST" action="" enctype="multipart/form-data" class="p-8 space-y-5">
            @csrf
            @method('PUT')

            <input type="hidden" name="remove_photo" id="removePhotoFlag" value="0">

            <div class="flex items-center space-x-4 mb-2">
                <div class="relative">
                    <div class="w-16 h-16 bg-stone-200/70 rounded-full flex items-center justify-center text-stone-600 overflow-hidden" id="editPhotoPreviewContainer">
                        <i class="fa-solid fa-user text-xl" id="editDefaultUserIcon"></i>
                        <img id="editPreviewImage" src="" alt="" class="w-full h-full object-cover hidden">
                    </div>
                    <div class="absolute bottom-0 right-0 w-6 h-6 bg-[#1e2e3e] text-white rounded-full flex items-center justify-center text-[10px] border-2 border-white">
                        <i class="fa-solid fa-camera"></i>
                    </div>
                </div>

                <input type="file" id="editPhotoInput" name="photo" accept="image/*" class="hidden" onchange="previewEditUserPhoto(event)">

                <div class="flex items-center space-x-2">
                    <button type="button" onclick="document.getElementById('editPhotoInput').click()" class="px-4 py-2 border border-stone-300 rounded-full text-xs font-medium text-stone-700 hover:bg-stone-50 transition">
                        Alterar foto
                    </button>
                    <button type="button" id="editRemovePhotoButton" onclick="removeEditUserPhoto()" class="hidden px-3 py-2 border border-rose-200 text-rose-600 rounded-full text-xs font-medium hover:bg-rose-50 transition">
                        Remover
                    </button>
                </div>
            </div>

            <div>
                <label class="block text-xs font-medium text-stone-700 mb-1.5">Nome completo</label>
                <input type="text" id="modalName" name="name" required class="w-full px-4 py-2.5 bg-white border border-stone-200 rounded-xl text-sm text-stone-800 focus:outline-none focus:ring-1 focus:ring-stone-400 transition">
            </div>

            <div>
                <label class="block text-xs font-medium text-stone-700 mb-1.5">Código</label>
                <input type="text" id="modalCode" name="code" readonly class="w-full px-4 py-2.5 bg-stone-100 border border-stone-200/80 rounded-xl text-sm text-stone-500 focus:outline-none cursor-not-allowed">
            </div>

            <div>
                <label class="block text-xs font-medium text-stone-700 mb-1.5">Unidade</label>
                <div class="relative">
                    <select id="modalUnit" name="unit" class="w-full px-4 py-2.5 bg-white border border-stone-200 rounded-xl text-sm text-stone-800 appearance-none focus:outline-none focus:ring-1 focus:ring-stone-400 transition pr-10">
                        <option value="Garden Ville">Garden Ville</option>
                        <option value="Menino Deus">Menino Deus</option>
                    </select>
                    <div class="absolute right-4 top-1/2 -translate-y-1/2 text-stone-500 pointer-events-none">
                        <i class="fa-solid fa-chevron-down text-xs"></i>
                    </div>
                </div>
            </div>

            <div>
                <label class="block text-xs font-medium text-stone-700 mb-1.5">Grupo</label>
                <div class="relative">
                    <select id="modalGroup" name="group" class="w-full px-4 py-2.5 bg-white border border-stone-200 rounded-xl text-sm text-stone-800 appearance-none focus:outline-none focus:ring-1 focus:ring-stone-400 transition pr-10">
                        <option value="Enfermagem GV">Enfermagem GV</option>
                        <option value="Farmácia MD">Farmácia MD</option>
                        <option value="Cuidadores GV">Cuidadores GV</option>
                        <option value="Gerente Geral">Gerente Geral</option>
                        <option value="Administradores de Área">Administradores de Área</option>
                        <option value="Auxiliar de Cozinha MD">Auxiliar de Cozinha MD</option>
                    </select>
                    <div class="absolute right-4 top-1/2 -translate-y-1/2 text-stone-500 pointer-events-none">
                        <i class="fa-solid fa-chevron-down text-xs"></i>
                    </div>
                </div>
            </div>

            <div>
                <label class="block text-xs font-medium text-stone-700 mb-1.5">Perfil</label>
                <div class="relative">
                    <select id="modalProfile" name="profile" class="w-full px-4 py-2.5 bg-white border border-stone-200 rounded-xl text-sm text-stone-800 appearance-none focus:outline-none focus:ring-1 focus:ring-stone-400 transition pr-10">
                        <option value="Enfermagem">Enfermagem</option>
                        <option value="Farmácia">Farmácia</option>
                        <option value="Cuidador">Cuidador</option>
                        <option value="Gerente">Gerente</option>
                        <option value="Administrador">Administrador</option>
                    </select>
                    <div class="absolute right-4 top-1/2 -translate-y-1/2 text-stone-500 pointer-events-none">
                        <i class="fa-solid fa-chevron-down text-xs"></i>
                    </div>
                </div>
            </div>

            <div>
                <label class="block text-xs font-medium text-stone-700 mb-1.5">Nova senha</label>
                <div class="relative">
                    <input type="password" id="modalPassword" name="password" placeholder="Digite para alterar a senha" class="w-full pl-4 pr-10 py-2.5 bg-white border border-stone-200 rounded-xl text-sm text-stone-800 focus:outline-none focus:ring-1 focus:ring-stone-400 transition">
                    <button type="button" onclick="togglePasswordVisibility()" class="absolute right-3.5 top-1/2 -translate-y-1/2 text-stone-400 hover:text-stone-600 transition">
                        <i id="passwordEyeIcon" class="fa-regular fa-eye text-sm"></i>
                    </button>
                </div>
            </div>

            <div class="pt-4 flex items-center space-x-3 border-t border-stone-100">
                <button type="button" onclick="closeEditModal()" class="w-1/2 py-2.5 border border-stone-300 rounded-xl text-sm font-medium text-stone-700 hover:bg-gray-50 transition text-center">
                    Cancelar
                </button>
                <button type="submit" class="w-1/2 py-2.5 bg-[#1e2e3e] hover:bg-[#15212d] text-white rounded-xl text-sm font-medium transition flex items-center justify-center space-x-2">
                    <i class="fa-solid fa-check text-xs"></i>
                    <span>Salvar</span>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function previewEditUserPhoto(event) {
        const file = event.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const preview = document.getElementById('editPreviewImage');
                const icon = document.getElementById('editDefaultUserIcon');
                const removeBtn = document.getElementById('editRemovePhotoButton');
                preview.src = e.target.result;
                preview.classList.remove('hidden');
                icon.classList.add('hidden');
                removeBtn.classList.remove('hidden');
                document.getElementById('removePhotoFlag').value = '0';
            }
            reader.readAsDataURL(file);
        }
    }

    function removeEditUserPhoto() {
        const input = document.getElementById('editPhotoInput');
        const preview = document.getElementById('editPreviewImage');
        const icon = document.getElementById('editDefaultUserIcon');
        const removeBtn = document.getElementById('editRemovePhotoButton');
        
        input.value = '';
        preview.src = '';
        preview.classList.add('hidden');
        icon.classList.remove('hidden');
        removeBtn.classList.add('hidden');
        document.getElementById('removePhotoFlag').value = '1';
    }
</script>