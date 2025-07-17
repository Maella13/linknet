let signup = document.getElementById("signup");
let username = document.getElementById("Username")
let mailid = document.getElementById("mailid");
let gender = document.getElementById("gender");
let pass = document.getElementById("pass");
let sendotp = document.getElementById("sendotp");
let parent = document.getElementById("parent");
var sendotpclickcount = 0;
console.dir(sendotp)
var sendotpcount=0;
sendotp.addEventListener("click", () => {
    
    sendotp.innerHTML="<b>Envoi...</b>"
    let mailidval = mailid.value;
    
    console.log(mailid.value)
    $.ajax({
        type: "POST",
        url: "_signup.php",
        data: {
            sendotp: true,
            mailid: mailidval,

        },

        success: function (response) {
            sendotp.innerHTML="Renvoyer le code"
            if (response.match("Something Went Wrong")) {

                Swal.fire({
                    icon: "error",
                    title: "Erreur...",
                    text: "Une erreur est survenue",
                });
            }
            if (response.match("Your OTP is send")) {

                Swal.fire({
                    icon: "success",
                    title: "Code envoyé",
                    text: response,

                });
                if (sendotpclickcount > 0) {

                } else {
                    let newelemet = document.createElement("div");
                    flagsucesssendotp = true;
                    let passinbox = `<br><div class="field">
                            <span class="fa fa-lock"></span>
                            <input class="otp" id="otp" name="pass" type="text" maxlength="4" placeholder="Entrez le code de vérification" required>
                            </div>`;
                            newelemet.innerHTML=passinbox;
                            parent.append(newelemet);
                            sendotpclickcount=sendotpclickcount+1;
                            sendotpcount=sendotpcount+1;
                            
                }
            }
        }
    })
})
signup.addEventListener("click", (e) => {
    if(sendotpcount<=0){
        Swal.fire({
            icon: "error",
            title: "Erreur...",
            text: "Veuillez d'abord envoyer le code avant de vous inscrire",
    
        });
    }else{
    let otpbtn=document.getElementById("otp")
    e.preventDefault()
   let mailval=mailid.value
   let userval=username.value
   let genderval=gender.value
   let passwordval=pass.value
   let otpval=otpbtn.value
   if(mailval=="" || userval=="" || genderval=="" || passwordval=="" || otpval=="" ){
    Swal.fire({
        icon: "error",
        title: "Erreur...",
        text: "Veuillez remplir tous les champs",

    });
   }else{
    $.ajax({
        type: "POST",
        url: "_signup.php",
        data: {
            signup: true,
            mailid: mailval,
            username: userval,
            gender: genderval,
            password: passwordval,
            otp: otpval,
    
        },
    
        success: function (response) {
            if(response.match("Incorect OTP")){
                Swal.fire({
                    icon: "error",
                    title: "Erreur...",
                    text: "Code de vérification incorrect",
            
                });
            }
            if(response.match("Fill All The inputs")){
                Swal.fire({
                    icon: "error",
                    title: "Erreur...",
                    text: "Veuillez remplir tous les champs",
            
                });
            }
            if(response.match("Username Is Already Resistered Please try another")){
                Swal.fire({
                    icon: "error",
                    title: "Erreur...",
                    text: "Nom d'utilisateur déjà utilisé, veuillez en choisir un autre",
            
                });
            }
            if(response.match("Some Error Occured")){
                Swal.fire({
                    icon: "error",
                    title: "Erreur...",
                    text: "Une erreur est survenue",
            
                });
            }
            if(response.match("SignUp Successfull")){
                Swal.fire({
                    icon: "success",
                    title: "Inscription réussie",
                    text: "Inscription réussie, vous pouvez vous connecter",
            
                });
            }
            if(response.match("Mailid Is Already Resistered Please try another")){
                Swal.fire({
                    icon: "error",
                    title: "Erreur...",
                    text: "Adresse mail déjà utilisée, veuillez en choisir une autre",
            
                });
            
        }
        if(response.match("please dont change mail after send otp")){
            Swal.fire({
                icon: "error",
                title: "Erreur...",
                text: "Veuillez ne pas changer l'adresse mail après l'envoi du code",
        
            });
        }
        }
    })
   }
}
})
