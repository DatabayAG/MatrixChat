document.addEventListener("DOMContentLoaded", () => {
        let config = {
            status: {
                invite: ""
            }
        };
        const init = () => {
            config = window.chatMembersTableConfig;
            const inviteButtons = document.querySelectorAll(".inviteButton-wrapper > button");
            inviteButtons.forEach((inviteButton) => {
                //Clearing all events
                const inviteButtonClone = inviteButton.cloneNode(true);
                inviteButton.parentElement.replaceChild(inviteButtonClone, inviteButton);
                inviteButton = inviteButtonClone;
                inviteButton.addEventListener("click", (event) => {
                    event.preventDefault();
                    onInviteButtonClick(event.target);
                })
            })
        }

        const onInviteButtonClick = async (inviteButton) => {
            let actionUrl = inviteButton.getAttribute("data-action");

            if (!actionUrl) {
                return;
            }

            const response = await fetch(actionUrl, {
                method: "GET"
            }).then((response) => {
                return response.json();
            })

            if (response.error) {
                console.error(response.error);
            } else {
                const chatStatus = inviteButton.parentElement.parentElement.parentElement.querySelector("td > a.glyph");
                if (chatStatus) {
                    chatStatus.parentElement.innerHTML = config.status.invite;
                }
                inviteButton.remove();
            }
        }
        init();
    }
);