document.addEventListener("DOMContentLoaded", () => {
        let config = {
            status: {
                invite: ""
            }
        };
        let init = () => {
            config = window.chatMembersTableConfig;
            let inviteButtons = document.querySelectorAll(".inviteButton-wrapper > button");
            inviteButtons.forEach((inviteButton) => {
                //Clearing all events
                let inviteButtonClone = inviteButton.cloneNode(true);
                inviteButton.parentElement.replaceChild(inviteButtonClone, inviteButton);
                inviteButton = inviteButtonClone;
                inviteButton.addEventListener("click", (event) => {
                    event.preventDefault();
                    onInviteButtonClick(event.target);
                })
            })
        }

        let onInviteButtonClick = async (inviteButton) => {
            let actionUrl = inviteButton.getAttribute("data-action");

            if (!actionUrl) {
                return;
            }

            let response = await fetch(actionUrl, {
                method: "GET"
            }).then((response) => {
                return response.json();
            })

            if (response.error) {
                console.error(response.error);
            } else {
                let chatStatus = inviteButton.parentElement.parentElement.parentElement.querySelector("td > a.glyph");
                if (chatStatus) {
                    chatStatus.parentElement.innerHTML = config.status.invite;
                }
                inviteButton.remove();
            }
        }
        init();
    }
);