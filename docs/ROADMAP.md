# Roadmap

## Device verification

Devices have to be verified by another device,   
otherwise the new device is unable to read previous encrypted messages.

## Allow Enabling encryption
It's not possible to enable encryption using the API (meaning it's not possible to enable through PHP code).  
Encryption has to be enabled by a user in the chat (through the matrix javascript sdk).

Once enabled, encryption can never be disabled again.

## Styling/Design
Depends on customer requirements.

## Chat features (reactions, replies, mentions)
Depends on customer requirements.

## Supporting further message types
Matrix supports different message types by default (not restricted to )
- text
- image
- audio
- video
- location
- emote

depending on customer requirements, these message types should be supported.

## Chat message
For ease of prototyping, the current matrix chat prototype supports only text messages.
These text messages are written in markdown format.

This should probably be changed for further versions and the features like styling text   
``code``,   
~~strike through~~,  
**bold**,  
*italic*,  
...

should be implemented themselves, instead of through markdown to conform with other Chat-Clients like Element.

## Designing/Implementing the chat

Currently, the entire chat is placed in a single long .js file.

For ease of programming / expansion this should maybe be changed to using something like REACT to be able to split the code into separate ``components``.