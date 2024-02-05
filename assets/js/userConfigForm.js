document.addEventListener("DOMContentLoaded", () => {
        let config = {};
        const init = () => {
            config = window.userConfigFormConfig;

            const specifyOtherMatrixAccountAuthMethod = document.querySelector("#subform_authMethod_specifyOtherMatrixAccount")
            const matrixAccountInputField = specifyOtherMatrixAccountAuthMethod.querySelector("div > div > input#matrixAccount");
            const checkAccountButton = document.createElement("button");
            checkAccountButton.innerText = config.translation.checkAccountOnMatrixServer;
            checkAccountButton.classList = "btn btn-default btn-sm";
            const message = document.createElement("span");
            message.style.visibility = "hidden";
            message.innerText = "info_text"
            message.classList = "col-sm-9";

            checkAccountButton.addEventListener("click", (event) => {
                event.preventDefault();
                message.style.visibility = "hidden";
                onCheckExternalAccount(matrixAccountInputField.value, message);
            });
            matrixAccountInputField.parentElement.insertBefore(checkAccountButton, matrixAccountInputField.nextSibling);

            const inputWrapper = checkAccountButton.parentElement.parentElement;
            inputWrapper.append(message);
        }

        const onCheckExternalAccount = async (value, message) => {
            if (!value || !value.startsWith("@")) {
                return;
            }

            const response = await fetch(config.actions.checkAccountOnMatrixServer, {
                method: "POST",
                body: JSON.stringify({matrixUserId: value})
            }).then((response) => {
                return response.json();
            })

            const result = response.result;
            message.innerText = response.message[result];
            message.style.visibility = "visible";
            message.style.color = result === "failure" ? "red" : "green";
            if (response.message.info) {
                const responseMessage = document.createElement("span");
                responseMessage.style.color = "blue";
                responseMessage.innerText = response.message.info;
                message.innerHTML = "<br>";
                message.append(responseMessage);
            }
        };

        il.Util.addOnLoad(init);
    }
);