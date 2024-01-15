document.addEventListener("DOMContentLoaded", () => {
        let config = {};
        let init = () => {
            config = window.userConfigFormConfig;

            let specifyOtherMatrixAccountAuthMethodElm = document.querySelector("#subform_authMethod_specifyOtherMatrixAccount")
            let matrixAccountInputFieldElm = specifyOtherMatrixAccountAuthMethodElm.querySelector("div > div > input#matrixAccount");
            let checkAccountButtonElm = document.createElement("button");
            checkAccountButtonElm.innerText = config.translation.checkAccountOnMatrixServer;
            checkAccountButtonElm.classList = "btn btn-default btn-sm";
            let messageElm = document.createElement("span");
            messageElm.style.visibility = "hidden";
            messageElm.innerText = "info_text"
            messageElm.classList = "col-sm-9";

            checkAccountButtonElm.addEventListener("click", (event) => {
                event.preventDefault();
                messageElm.style.visibility = "hidden";
                onCheckExternalAccount(matrixAccountInputFieldElm.value, messageElm);
            });
            matrixAccountInputFieldElm.parentElement.insertBefore(checkAccountButtonElm, matrixAccountInputFieldElm.nextSibling);

            let inputWrapperElm = checkAccountButtonElm.parentElement.parentElement;
            inputWrapperElm.append(messageElm);
        }

        let onCheckExternalAccount = async (value, messageElm) => {
            if (!value || !value.startsWith("@")) {
                return;
            }

            let response = await fetch(config.actions.checkAccountOnMatrixServer, {
                method: "POST",
                body: JSON.stringify({matrixUserId: value})
            }).then((response) => {
                return response.json();
            })

            console.log(response);
            let result = response.result;
            messageElm.innerText = response.message[result];
            messageElm.style.visibility = "visible";
            messageElm.style.color = result === "failure" ? "red" : "green";
            if (response.message.info) {
                messageElm.innerHTML += `<br><span style='color: blue'>${response.message.info}</span>`
            }
        };

        il.Util.addOnLoad(init);
    }
);