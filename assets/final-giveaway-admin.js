'use strict';
const prizeSelect = document.getElementById('final-prize');
function showPrizeDelivery() {
 const target=document.getElementById('final-delivery-preview');
 if(target&&prizeSelect)target.textContent=prizeSelect.selectedOptions[0]?.dataset.delivery||'';
}
if(prizeSelect){prizeSelect.addEventListener('change',showPrizeDelivery);showPrizeDelivery();}
const drawForm=document.getElementById('final-draw-form');
if(drawForm)drawForm.addEventListener('submit',event=>{
 const selected=event.submitter;
 if(!selected)return;
 // Preserve the chosen action before disabling buttons to prevent repeated clicks.
 const action=document.createElement('input');action.type='hidden';action.name='action';action.value=selected.value;drawForm.append(action);
 drawForm.querySelectorAll('button[type="submit"]').forEach(button=>button.disabled=true);
 selected.textContent='Saving real drawing…';
});
