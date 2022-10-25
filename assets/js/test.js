document.addEventListener("DOMContentLoaded", () => {
    let client;
    let chatContainerElement;
    let markdownRenderer;
    let easyMDE;
    let firstRoomEvent;


    let matrixChatConfig = {
      baseUrl: "",
      ajax: {
        getTemplateAjax: ""
      },
      user: {
        accessToken: "",
        matrixUserId: "",
        iliasUserId: "",
        deviceId: "",
      },
      roomId: ""
    };

    let templates = {
      getTemplate: async function (templateName, data = {}) {
        if (!templates.storage[templateName]) {
          templates.storage[templateName] = await fetch(
            matrixChatConfig.ajax.getTemplateAjax + `&templateName=tpl.${templateName}.html`)
          .then(response => response.text());
        }

        let templateHtml = templates.storage[templateName];
        Object.keys(data).forEach(key => {
          templateHtml = templateHtml.replaceAll("{% " + key + " %}", data[key]);
        })

        return templateHtml;
      },
      storage: {}
    }

    let translation = {};

    let init = async () => {
      markdownRenderer = window.markdownit();
      markdownRenderer.options.breaks = true;

      matrixChatConfig = window.matrixChatConfig;
      translation = JSON.parse(window.matrixChatTranslation);
      chatContainerElement = document.querySelector(".chat-messages-container");
      chatContainerElement.addEventListener("scroll", (event) => {
        if (chatContainerElement.scrollTop === 0) {
          paginate();
        }
      })

      easyMDE = new EasyMDE({
        element: document.getElementById('chat-message-writer-message'),
        toolbar: [
          "bold",
          "italic",
          "quote",
          "unordered-list",
          "ordered-list",
          "link",
          "image",
          {
            name: "custom",
            action: (editor) => {
              let value = easyMDE.value();
              if (value) {
                onSendMessage(easyMDE.value());
                easyMDE.value("");
              }
            },
            className: "chat-message-writer-send",
            text: translation.matrix.chat.send,
          },
        ]
      });

      document.addEventListener("keydown", (event) => {
        let value = easyMDE.value();

        if (
          value
          && event.key === "Enter"
          && !event.ctrlKey
          && !event.altKey
          && !event.shiftKey
          && !event.metaKey) {
          onSendMessage(easyMDE.value());
          easyMDE.value("");
        }
      });

      if (!chatContainerElement) {
        console.error("Chat messages container not found!");
        return;
      }

      client = await initClient();
      client.setGlobalErrorOnUnknownDevices(false); //Not recommended

      client.on("Room.timeline", async function (event, room, toStartOfTimeline) {
        if (room.roomId !== matrixChatConfig.roomId) {
          return;
        }
        if (!firstRoomEvent) {
          firstRoomEvent = event;
        }

        let content = event.getContent();

        switch (event.getType()) {
          case "m.room.encrypted":
          case "m.room.message":
            await client.decryptEventIfNeeded(event, { isRetry: false, emit: false });
            switch (content.msgtype) {
              case "m.text":
                await onAddMessage(event, toStartOfTimeline);
                break;
              case "m.image":
                await onAddImageMessage(event, toStartOfTimeline);
                break;
              case "m.audio":
              case "m.video":
              case "m.location":
              case "m.emote":
                break;
            }
            break;
          case "m.room.member":
            if (content.membership === "join" && content.displayname) {
              let previousName = "";
              document.querySelectorAll(
                `div[sender="${event.sender.userId}"]`)
              .forEach((messageContainerElm) => {
                let authorElm = messageContainerElm.querySelector(".chat-message-author");
                if (authorElm) {
                  previousName = authorElm.innerText;
                  authorElm.innerText = content.displayname;
                }
              });

              if (previousName !== "") {
                await addNotificationMessage(
                  translation.matrix.chat.notifications.changedName
                  .replace("%s", previousName)
                  .replace("%s", content.displayname)
                );
              }
            }
            break;
          default:
            console.log(event);
            break;
        }
      });
      await client.startClient({ initialSyncLimit: 10 });
    }


    let onSendMessage = (message) => {
      client.sendEvent(
        matrixChatConfig.roomId,
        'm.room.message',
        {
          msgtype: 'm.text',
          format: 'org.matrix.custom.html',
          body: message,
          formatted_body: message,
        },
        ''
      ).then((ere) => {
        //console.log(ere);
      })
    }

    let addNotificationMessage = async (message) => {
      let element = document.createElement("template");
      element.innerHTML = (await templates.getTemplate("chatNotification", { message: message }));

      chatContainerElement.appendChild(element.content.firstChild);
      chatContainerElement.scrollTop = chatContainerElement.scrollHeight;
    }

    let onAddMessage = async (event, prepend = false) => {
      let date = event.getDate();

      let element = document.createElement("template");
      element.innerHTML = (await templates.getTemplate("chatMessage", {
        eventId: event.getId(),
        author: event.sender.name,
        date: dateToString(date),
        message: markdownRenderer.render(event.getContent().body)
      }));

      if (prepend) {
        chatContainerElement.insertBefore(element.content.firstChild,
          chatContainerElement.firstChild);
      } else {
        chatContainerElement.appendChild(element.content.firstChild);
        chatContainerElement.scrollTop = chatContainerElement.scrollHeight;
      }
    }

    let onAddImageMessage = async (event, prepend = false) => {
      let date = event.getDate();
      let content = event.getContent();
      let url = client.mxcUrlToHttp(content, 400, 400, "scale", false);

      let element = document.createElement("template");
      element.setAttribute("src", url);
      element.innerHTML = (await templates.getTemplate("chatImageMessage", {
        eventId: event.getId(),
        src: url,
        altText: content.body,
        author: event.sender.name,
        date: dateToString(date),
      }));
      if (prepend) {
        chatContainerElement.insertBefore(element.content.firstChild,
          chatContainerElement.firstChild);
      } else {
        chatContainerElement.appendChild(element.content.firstChild);
        chatContainerElement.scrollTop = chatContainerElement.scrollHeight;
      }
    }

    let initClient = async () => {
      let client = matrixcs.createClient({
        baseUrl: matrixChatConfig.baseUrl,
        accessToken: matrixChatConfig.user.accessToken,
        userId: matrixChatConfig.user.matrixUserId,
        deviceId: matrixChatConfig.user.deviceId,
        timelineSupport: true,
      });
      return client.initCrypto()
      .then(() => {
        client.once('sync', function (state, prevState, res) {
          if (state !== 'PREPARED') {
            process.exit(1);
          }


        });

        return client;
      });
    }

    let paginate = () => {
      let currentTopMessageElm = chatContainerElement.children[0];

      const room = client.getRoom(matrixChatConfig.roomId);
      const tls = room.getTimelineSets()[0];
      client
      .getEventTimeline(tls, firstRoomEvent.event.event_id)
      .then(et => client.paginateEventTimeline(et, { backwards: true, limit: 10 }))
      .then(success => {
        //Scroll to previously first message element.
        if (success && currentTopMessageElm) {
          currentTopMessageElm.scrollIntoView();
        }
      });
    }

    let dateToString = (date) => {
      return date.toLocaleDateString("de-DE", {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit'
      });
    };

    il.Util.addOnLoad(init);
  }
)
;