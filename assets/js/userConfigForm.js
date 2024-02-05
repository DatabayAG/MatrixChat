document.addEventListener("DOMContentLoaded", () => {
        let config = {};
        let init = () => {
            config = window.userConfigFormConfig;

            let specifyOtherMatrixAccountAuthMethod = document.querySelector("#subform_authMethod_specifyOtherMatrixAccount")
            let matrixAccountInputField = specifyOtherMatrixAccountAuthMethod.querySelector("div > div > input#matrixAccount");
            let checkAccountButton = document.createElement("button");
            checkAccountButton.innerText = config.translation.checkAccountOnMatrixServer;
            checkAccountButton.classList = "btn btn-default btn-sm";
            let message = document.createElement("span");
            message.style.visibility = "hidden";
            message.innerText = "info_text"
            message.classList = "col-sm-9";

            checkAccountButton.addEventListener("click", (event) => {
                event.preventDefault();
                message.style.visibility = "hidden";
                onCheckExternalAccount(matrixAccountInputField.value, message);
            });
            matrixAccountInputField.parentElement.insertBefore(checkAccountButton, matrixAccountInputField.nextSibling);

            let inputWrapper = checkAccountButton.parentElement.parentElement;
            inputWrapper.append(message);
        }

        let onCheckExternalAccount = async (value, message) => {
            if (!value || !value.startsWith("@")) {
                return;
            }

            let response = await fetch(config.actions.checkAccountOnMatrixServer, {
                method: "POST",
                body: JSON.stringify({matrixUserId: value})
            }).then((response) => {
                return response.json();
            })

            let result = response.result;
            message.innerText = response.message[result];
            message.style.visibility = "visible";
            message.style.color = result === "failure" ? "red" : "green";
            if (response.message.info) {
                message.innerHTML += `<br><span style='color: blue'>${response.message.info}</span>`
            }
        };

        init();
    }
);