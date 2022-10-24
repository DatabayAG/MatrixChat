document.addEventListener("DOMContentLoaded", () => {
  let client;
  let chatContainerElement;
  let chatMessageWriterInputElement;
  let chatMessageWriterSendButtonElement;
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
    storage: {

    }
  }

  let translation = {

  }

  let init = async () => {
    markdownRenderer = window.markdownit();
    console.log(markdownRenderer);
    matrixChatConfig = window.matrixChatConfig;
    translation = JSON.parse(window.matrixChatTranslation);
    chatContainerElement = document.querySelector(".chat-messages-container");
    chatMessageWriterInputElement = document.querySelector(".chat-message-writer-message");
    chatMessageWriterSendButtonElement = document.querySelector(".chat-message-writer-send");

    chatMessageWriterSendButtonElement.addEventListener("click", () => {
      let value = easyMDE.value();
      if (value) {
        onSendMessage(easyMDE.value());
        easyMDE.value("");
      }
    })

    easyMDE = new EasyMDE({element: document.getElementById('chat-message-writer-message')});


    if (!chatContainerElement) {
      console.error("Chat messages container not found!");
      return;
    }

    client = await initClient();
    client.setGlobalErrorOnUnknownDevices(false); //Not recommended

    client.once('sync', function(state, prevState, res) {
      if(state !== 'PREPARED') {
        process.exit(1);
      }
    });
    client.on("Room.timeline", async function(event, room, toStartOfTimeline) {
      let content = event.getContent();

      switch (event.getType()) {
        case "m.room.encrypted":
        case "m.room.message":
          await client.decryptEventIfNeeded(event, {isRetry: false, emit: false});
          switch (content.msgtype)
          {
            case "m.text":
              onAddMessage(event.sender, content.body, content.msgtype);
              break;
            case "m.image":
              let a = client.mxcUrlToHttp(content.url, 400, 400, "scale", false);
              console.log(a);
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

  let onAddMessage = async (sender, message, messageType) => {
    let element = document.createElement("div");
    element.setAttribute("sender", sender.userId);

    element.innerHTML = (await templates.getTemplate("chatMessage"))
    .replaceAll("{% author %}", sender.name)
    .replaceAll("{% date %}", "15.08.20000")
    .replaceAll("{% message %}", markdownRenderer.render(message));
    chatContainerElement.appendChild(element);
    chatContainerElement.scrollTop = chatContainerElement.scrollHeight;
  }

  let onAddImageMessage = async (sender, url, altText) => {
    let element = document.createElement("img");
    element.setAttribute("src", url);
    element.innerHTML = (await templates.getTemplate("chatImageMessage"));
    element.setAttribute("alt", altText);
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
});