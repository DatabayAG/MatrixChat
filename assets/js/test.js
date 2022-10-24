document.addEventListener("DOMContentLoaded", () => {
    let client;
    let chatContainerElement;
    let chatMessageWriterInputElement;
    let markdownRenderer;
    let easyMDE;
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
      getTemplate: async function (templateName) {
        if (!templates.storage[templateName]) {
          templates.storage[templateName] = await fetch(
            matrixChatConfig.ajax.getTemplateAjax + `&templateName=tpl.${templateName}.html`)
          .then(response => response.text());
        }

        return templates.storage[templateName] ?? "";
      },
      storage: {}
    }

    let translation = {}

    let init = async () => {
      markdownRenderer = window.markdownit();
      markdownRenderer.options.breaks = true;
      console.log(markdownRenderer);
      matrixChatConfig = window.matrixChatConfig;
      translation = JSON.parse(window.matrixChatTranslation);
      chatContainerElement = document.querySelector(".chat-messages-container");
      chatMessageWriterInputElement = document.querySelector(".chat-message-writer-message");

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

      client.once('sync', function (state, prevState, res) {
        if (state !== 'PREPARED') {
          process.exit(1);
        }
      });
      client.on("Room.timeline", async function (event, room, toStartOfTimeline) {
        let content = event.getContent();

        switch (event.getType()) {
          case "m.room.encrypted":
          case "m.room.message":
            await client.decryptEventIfNeeded(event, { isRetry: false, emit: false });
            switch (content.msgtype) {
              case "m.text":
                onAddMessage(event.sender, content.body);
                break;
              case "m.image":
                onAddImageMessage(
                  event.sender,
                  client.mxcUrlToHttp(content.url, 400, 400, "scale", false),
                  content.body
                );
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
                addNotificationMessage(
                  translation.matrix.chat.notifications.changedName
                  .replace("%s", previousName)
                  .replace("%s", content.displayname)
                );
              }
            }
            break;
          default:
        }
        console.log(event);
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
      let element = document.createElement("div");
      element.innerHTML = (await templates.getTemplate("chatNotification"))
      .replaceAll("{% message %}", message);
      chatContainerElement.appendChild(element);
      chatContainerElement.scrollTop = chatContainerElement.scrollHeight;
    }

    let onAddMessage = async (sender, message) => {
      let element = document.createElement("div");
      element.setAttribute("sender", sender.userId);
      console.log();
      element.innerHTML = (await templates.getTemplate("chatMessage"))
      .replaceAll("{% author %}", sender.name)
      .replaceAll("{% date %}", "15.08.20000")
      .replaceAll("{% message %}", markdownRenderer.render(message));
      chatContainerElement.appendChild(element);
      chatContainerElement.scrollTop = chatContainerElement.scrollHeight;
    }

    let onAddImageMessage = async (sender, url, altText) => {
      let element = document.createElement("div");
      element.setAttribute("src", url);
      element.innerHTML = (await templates.getTemplate("chatImageMessage"))
      .replaceAll("{% src %}", url)
      .replaceAll("{% altText %}", altText)
      .replaceAll("{% author %}", sender.name)
      .replaceAll("{% date %}", "15.08.20000")
      chatContainerElement.appendChild(element);
      chatContainerElement.scrollTop = chatContainerElement.scrollHeight;
    }

    let initClient = async () => {
      let client = matrixcs.createClient({
        baseUrl: matrixChatConfig.baseUrl,
        accessToken: matrixChatConfig.user.accessToken,
        userId: matrixChatConfig.user.matrixUserId,
        deviceId: matrixChatConfig.user.deviceId,
      });
      return client.initCrypto()
      .then(() => {
        return client;
      });
    }

    il.Util.addOnLoad(init);
  }
)
;