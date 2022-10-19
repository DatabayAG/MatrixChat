document.addEventListener("DOMContentLoaded", () => {
  let client;
  let chatContainerElement;
  let chatMessageWriterInputElement;
  let chatMessageWriterSendButtonElement;
  let matrixChatConfig = {
    baseUrl: "",
    ajax: {
      getTemplateAjax: ""
    },
    user: {
      accessToken: "",
      userId: "",
      deviceId: "",
    },
    roomId: ""
  };

  let data = {
    templates: {
      chatMessage: ""
    }
  };


  let init = async () => {
    matrixChatConfig = window.matrixChatConfig;
    chatContainerElement = document.querySelector(".chat-messages-container");
    chatMessageWriterInputElement = document.querySelector(".chat-message-writer-message");
    chatMessageWriterSendButtonElement = document.querySelector(".chat-message-writer-send");

    chatMessageWriterSendButtonElement.addEventListener("click", () => onSendMessage(chatMessageWriterInputElement.value))


    if (!chatContainerElement) {
      console.error("Chat messages container not found!");
      return;
    }

    await loadTemplates();
    client = await initClient();
    client.setGlobalErrorOnUnknownDevices(false); //Not recommended

    for (let i = 0; i < 10; i++) {
      console.log(i + "--------------------------------");
    }

    client.once('sync', function(state, prevState, res) {
      if(state === 'PREPARED') {
        console.log("prepared");
      } else {
        console.log(state);
        process.exit(1);
      }
    });
    client.on("Room.timeline", async function(event, room, toStartOfTimeline) {
      if (event.getType() !== "m.room.encrypted") {
        console.log(event);
        return;
      }

      await client.decryptEventIfNeeded(event, {isRetry: false, emit: false});
      onAddMessage(event.getSender(), event.getContent().body, event.getContent().msgtype);
    });
    await client.startClient({ initialSyncLimit: 10 });
  }

  let loadTemplates = async () => {
    data.templates.chatMessage = await fetch(
      matrixChatConfig.ajax.getTemplateAjax + "&templateName=tpl.chat-message.html")
    .then(response => response.text()
    );
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
      console.log(ere);
    })
  }

  let onAddMessage = (sender, message, messageType) => {
    let element = document.createElement("div");
    element.innerHTML = data.templates.chatMessage
    .replaceAll("{% author %}", sender)
    .replaceAll("{% date %}", "15.08.20000")
    .replaceAll("{% message %}", message);
    chatContainerElement.appendChild(element);
  }

  let initClient = async () => {
    let client = matrixcs.createClient({
      baseUrl: matrixChatConfig.baseUrl,
      accessToken: matrixChatConfig.user.accessToken,
      userId: matrixChatConfig.user.userId,
      deviceId: matrixChatConfig.user.deviceId,
    });
    //let a = client.getRoom(window.matrixChatConfig.room_id);
    //console.log(window.matrixChatConfig.room_id);
    return client.initCrypto()
    .then(() => {
      return client;
    });
  }

  il.Util.addOnLoad(init);
});