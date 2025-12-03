document.addEventListener('DOMContentLoaded', () => {
  const INPUT_ID = 'customSpaceTitle';
  const init = () => {
    let observer = new MutationObserver(async (mutations) => {
      mutations.forEach((mutation) => {
        mutation.addedNodes.forEach((addedNode) => {

          const input = addedNode.parentElement.querySelector(`#${INPUT_ID}`);
          if (
            addedNode.classList.contains('c-form__autocomplete')
            && input
            && addedNode === addedNode.parentElement.lastChild
          ) {
            addedNode.parentElement.insertBefore(addedNode, input.nextSibling);
          }
        });
      });
    });

    observer.observe(document.body, {
      childList: true,
      subtree: true
    });
  };

  il.Util.addOnLoad(init);
});
