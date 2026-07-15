document.addEventListener('DOMContentLoaded', function() {
    console.log('Vapor hamburger script loaded');

    var toggleBtn = document.getElementById('vapor-menu-toggle');
    var navigation = document.getElementById('app-navigation');
    var content = document.getElementById('app-content');
    var hamburgerButton = document.getElementById('vapor-menu-toggle');

    console.log('toggleBtn:', toggleBtn);
    console.log('navigation:', navigation);
    console.log('content:', content);
    console.log('hamburgerButton:', hamburgerButton);

    if (toggleBtn && navigation && content && hamburgerButton) {
        console.log('All elements found, attaching click handler');

        var isOpen = false;

        function applyTransforms(open) {
            console.log('applyTransforms called with open =', open);
            if (open) {
                navigation.style.transform = 'translateX(0)';
                navigation.style.transition = 'transform 0.3s ease';
                hamburgerButton.style.transform = 'translateX(300)';
                hamburgerButton.style.transition = 'transform 0.3s ease';
                content.style.transform = 'translateX(300px)';
                content.style.transition = 'transform 0.3s ease';
            } else {
                navigation.style.transform = 'translateX(-300px)';
                navigation.style.transition = 'transform 0.3s ease';
                hamburgerButton.style.transform = 'translateX(0px)';
                hamburgerButton.style.transition = 'transform 0.3s ease';
                content.style.transform = 'translateX(0)';
                content.style.transition = 'transform 0.3s ease';
            }
        }

        applyTransforms(false);

        toggleBtn.addEventListener('click', function(e) {
            console.log('Hamburger button clicked');
            e.preventDefault();
            isOpen = !isOpen;
            applyTransforms(isOpen);
        });
    } else {
        console.error('Could not find one or more elements!');
    }
});
