document.addEventListener('DOMContentLoaded',()=>{
  const toggle=document.querySelector('.menu-toggle');
  const sidebar=document.querySelector('.sidebar');
  if(toggle&&sidebar){
    toggle.addEventListener('click',()=>{const open=sidebar.classList.toggle('open');toggle.setAttribute('aria-expanded',String(open));});
    document.addEventListener('click',event=>{if(!sidebar.contains(event.target)&&!toggle.contains(event.target)){sidebar.classList.remove('open');toggle.setAttribute('aria-expanded','false');}});
  }
  const supportType=document.querySelector('#support-type');
  const category=document.querySelector('#support-category');
  if(supportType&&category){
    const syncCategories=()=>{
      const type=supportType.value;
      const previous=category.value;
      for(const option of category.options){
        if(!option.value) continue;
        const matches=option.dataset.supportType===type;
        option.hidden=!matches;
        option.disabled=!matches;
      }
      category.disabled=!type;
      if(!type || !Array.from(category.options).some(option=>option.value===previous&&!option.disabled)) category.value='';
      category.options[0].textContent=type?'Selecciona una categoría':'Selecciona primero un tipo';
    };
    supportType.addEventListener('change',syncCategories);
    syncCategories();
  }
});
