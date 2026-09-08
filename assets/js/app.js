document.getElementById("year").textContent = new Date().getFullYear();
const menuBtn=document.querySelector(".menu-btn"), nav=document.querySelector(".nav");
menuBtn.addEventListener("click",()=>nav.classList.toggle("open"));
document.querySelectorAll(".nav a").forEach(a=>a.addEventListener("click",()=>nav.classList.remove("open")));

const form=document.getElementById("admissionForm"), msg=document.getElementById("formMsg");
form.addEventListener("submit", async e=>{
  e.preventDefault();
  msg.className="form-msg"; msg.textContent="Sending enquiry...";
  try{
    const res=await fetch("admin/api/admission.php",{method:"POST",body:new FormData(form)});
    const data=await res.json();
    if(data.success){msg.className="form-msg success";msg.textContent=data.message;form.reset();}
    else{msg.className="form-msg error";msg.textContent=data.message||"Unable to send. Please call the school.";}
  }catch(err){
    msg.className="form-msg error";
    msg.textContent="Demo mode: form endpoint is ready. Connect PHP/MySQL hosting to store enquiries.";
  }
});
