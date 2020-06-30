var ul = document.getElementById("users")

function populate(users) {
    while (ul.childNodes.length) {
        ul.removeChild(ul.childNodes[0])
    }
    for (const user of users) {
        var li = document.createElement('li')
        li.innerHTML = user
        ul.appendChild(li)
    }
    setTimeout(getUsers, 1000)
}

function getUsers() {
    var xmlhttp = new XMLHttpRequest()
    var url = "watch.php"

    xmlhttp.onreadystatechange = function () {
        if (this.readyState == 4 && this.status == 200) {
            var users = JSON.parse(this.responseText)
            populate(users)
        }
    }
    xmlhttp.open("GET", url, true)
    xmlhttp.send()
}

getUsers()