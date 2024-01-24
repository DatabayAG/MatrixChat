document.addEventListener("DOMContentLoaded", () => {
    let config = {
        status: {
            invite: ""
        }
    };
    let init = () => {
        config = window.chatMembersTableConfig;
            let inviteButtonElms = document.querySelectorAll(".inviteButton-wrapper > button");
            inviteButtonElms.forEach((inviteButtonElm) => {
                //Clearing all events
                let inviteButtonElmClone = inviteButtonElm.cloneNode(true);
                inviteButtonElm.parentElement.replaceChild(inviteButtonElmClone, inviteButtonElm);
                inviteButtonElm = inviteButtonElmClone;
                inviteButtonElm.addEventListener("click", (event) => {
                    event.preventDefault();
                    onInviteButtonClick(event.target);
                })
            })
        }

        let onInviteButtonClick = async (inviteButtonElm) => {
            let actionUrl = inviteButtonElm.getAttribute("data-action");

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
                let chatStatusElm = inviteButtonElm.parentElement.parentElement.parentElement.querySelector("td > a.glyph");
                if (chatStatusElm) {
                    chatStatusElm.parentElement.innerHTML = config.status.invite;
                }
                inviteButtonElm.remove();
            }
        }

        il.Util.addOnLoad(init);
    }
);