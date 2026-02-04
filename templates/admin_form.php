<script>
async function configureForm(org, form, type, name) {
    const btn = event.target;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Analyse...';
    
    try {
        const response = await fetch(`admin.php?action=analyze&org=${org}&form=${form}&type=${type}`);
        const items = await response.json();
        
        let html = `
            <div id="editor-container" class="mt-8 p-6 bg-slate-900 rounded-2xl border border-blue-500/30">
                <h3 class="text-lg font-bold mb-4 text-blue-400">Configuration : ${name}</h3>
                <table class="w-full text-xs">
                    <thead>
                        <tr class="text-slate-500 uppercase text-left">
                            <th class="p-2">Item HelloAsso</th>
                            <th class="p-2">Nom Affiché</th>
                            <th class="p-2">Type</th>
                            <th class="p-2">Groupe</th>
                            <th class="p-2">Graphique</th>
                        </tr>
                    </thead>
                    <tbody id="rules-body">
        `;

        items.forEach((item, i) => {
            html += `
                <tr class="border-t border-slate-800 rule-row" data-pattern="${item}">
                    <td class="p-2 font-mono text-slate-400">${item}</td>
                    <td class="p-2"><input type="text" class="display-label bg-slate-800 border border-slate-700 rounded px-2 py-1 w-full" value="${item}"></td>
                    <td class="p-2">
                        <select class="rule-type bg-slate-800 border border-slate-700 rounded px-1 py-1">
                            <option value="Billet">🎫 Billet</option>
                            <option value="Option">🍔 Option</option>
                            <option value="Info">ℹ️ Info</option>
                        </select>
                    </td>
                    <td class="p-2"><input type="text" class="rule-group bg-slate-800 border border-slate-700 rounded px-2 py-1 w-full" placeholder="Ex: Repas"></td>
                    <td class="p-2">
                        <select class="rule-chart bg-slate-800 border border-slate-700 rounded px-1 py-1">
                            <option value="Doughnut">🍩 Camembert</option>
                            <option value="Bar">📊 Barres</option>
                        </select>
                    </td>
                </tr>
            `;
        });

        html += `</tbody></table>
                <div class="mt-6 flex justify-end">
                    <button onclick="saveFullCampaign('${org}','${form}','${type}','${name}')" class="bg-emerald-600 hover:bg-emerald-500 px-8 py-3 rounded-full font-bold transition">
                        <i class="fa-solid fa-floppy-disk mr-2"></i> Créer le Dashboard
                    </button>
                </div>
            </div>`;
        
        document.querySelector('.bg-slate-800\\/50').innerHTML = html;
    } catch (e) {
        alert("Erreur lors de l'analyse. Vérifiez vos clés API.");
        btn.innerHTML = 'Configurer';
    }
}

async function saveFullCampaign(org, form, type, name) {
    const rules = [];
    document.querySelectorAll('.rule-row').forEach(row => {
        rules.push({
            pattern: row.dataset.pattern,
            displayLabel: row.querySelector('.display-label').value,
            type: row.querySelector('.rule-type').value,
            group: row.querySelector('.rule-group').value,
            chartType: row.querySelector('.rule-chart').value,
            transform: "" 
        });
    });

    const config = {
        slug: form,
        title: name,
        orgSlug: org,
        formSlug: form,
        formType: type,
        icon: "mask",
        rules: rules
    };

    const body = new FormData();
    body.append('save_campaign', '1');
    body.append('config', JSON.stringify(config));

    const res = await fetch('admin.php', { method: 'POST', body: body });
    const result = await res.json();
    if(result.success) window.location.href = 'index.php?msg=created';
}
</script>