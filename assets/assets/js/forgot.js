let otpbtn = document.getElementById("sendotp");
let parent=document.getElementById("form");
// console.log(parent)
var flagsucesssendotp=false;
otpbtn.addEventListener("click", (e) => {
  otpbtn.src = "assets/image/loading.gif";
  e.preventDefault();
});






var sendotpclickcount=0;
function aftersendotp(){
  if(flagsucesssendotp){
    // console.log("flagsucesssendotp activated")
  let submit_reset = document.getElementById("submit_reset");
  
  submit_reset.addEventListener("click", () => {
    submit_reset.innerHTML = "Chargement...";
    console.log(submit_reset)
  });
  submit_reset.addEventListener("click", () => {
    var username = $(".username").val();
    console.log(username)
    var otp = $(".otp").val();
    console.log(otp)
    var pass = $(".pass").val();
    console.log(pass)
    $.ajax({
      type: "POST",
      url: "_forgot.php",
      data: {
        validateotp: true,
        username: username,
        otp: otp,
        pass: pass,
      },
  
      success: function (response) {
        submit_reset.innerHTML = "Réinitialiser mon mot de passe";
        // console.log(response)
        if (response.match("Identifiant inexistant, veuillez contacter l'administrateur.")) {
          // console.log("id Is Not Exist Please Contact Admin");
          Swal.fire({
            icon: "error",
            title: "Erreur...",
            text: "Identifiant inexistant, veuillez contacter l'administrateur.",
          });
        }
        if (response.match("Mot de passe modifié avec succès")) {
          // console.log("Password Changed Successfully");
          Swal.fire({
            icon: "success",
            title: "Mot de passe modifié avec succès",
            text: "Votre mot de passe a été réinitialisé. Vous pouvez maintenant vous connecter.",
          });
        }
        if (response.match("Veuillez entrer le bon code OTP")) {
          // console.log("Please Enter The Correct Otp");
          Swal.fire({
            icon: "error",
            title: "Veuillez entrer le bon code OTP",
            // text: response,
          });
        }
      },
    });
  });
  }
}






otpbtn.addEventListener("click", () => {
  var username = $(".username").val();
  console.log(username)
  $.ajax({
    type: "POST",
    url: "_forgot.php",
    data: {
      checksendotp: true,
      username: username,
    },

    success: function (response) {
      otpbtn.src = "assets/image/send.png";

      if (response.match("Identifiant inexistant, veuillez contacter l'administrateur.")) {
        Swal.fire({
          icon: "error",
          title: "Erreur...",
          text: "Identifiant inexistant, veuillez contacter l'administrateur.",
        });
      }
      else if (response.match("Adresse mail non valide, veuillez contacter l'administrateur.")) {
        Swal.fire({
          icon: "error",
          title: "Erreur...",
          text: "Adresse mail non valide, veuillez contacter l'administrateur.",
        });
      }
      else if (response.match("Vous avez atteint la limite maximale de réinitialisations pour aujourd'hui.")) {
        Swal.fire({
          icon: "warning",
          title: "Réessayez demain",
          text: "Vous avez atteint la limite maximale de réinitialisations pour aujourd'hui.",
        });
      }
      else if (response.match("Code envoyé avec succès")) {

        Swal.fire({
          icon: "success",
          title: "Code envoyé avec succès",
          text: response,
        });
        if(sendotpclickcount>0){

        }else{

          let newelemet=document.createElement("div");
          flagsucesssendotp=true;
          let passinbox=`<div class="field">
          <span class="fa fa-lock"></span>
          <input class="otp" id="otp" name="pass" type="text" maxlength="4" placeholder="Entrez le code de vérification" required>
      </div>
      <div class="field" style="margin-top:10px">
        <span class="fa fa-lock"></span>
        <input class="pass" name="cpass" type="password" placeholder="Confirmez le mot de passe" required>
      </div>
      <button id="submit_reset" name="login">Réinitialiser mon mot de passe</button>`;
        newelemet.innerHTML=passinbox;
        parent.append(newelemet);
        sendotpclickcount=sendotpclickcount+1;
        aftersendotp()
      }
      }
      else{
        Swal.fire({
          icon: "error",
          title: "Erreur...",
          text: "Une erreur est survenue, veuillez réessayer plus tard.",
        });
      }
    },
  });
});
// submit_reset

