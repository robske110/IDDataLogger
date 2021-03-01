# Setting up the iOS Widget

The iOS widget is created using Scriptable App.

### Steps to get the script into the Scriptable App

1. Download Scriptable from the iOS App Store.
2. Download the file `Car.scriptable` from the latest [release](https://github.com/robske110/IDDatalogger/releases)
4. If you own a mac airdrop this file to your iOS device and open it in Scriptable. 
   
   On other operating system you'll need to send the file to your iOS device in another way, for example by e-mail.
3. Edit the file according to the instructions in [Changing settings in the script](#changing-settings-in-the-script)

### Changing settings in the script

#### Initial setup

To put the necessary API key and base URL into the script you'll need to open it in Scriptable.
To do this click the three dots on the Script entry in the Scriptable app.

You'll now need to find the line `const apiKey = ""` and place the API key you got during the setup phase of the IDDataLogger between the "".

After that you need to place the baseURL between the "" in the line which says `const baseURL = ""`.

If you have used the install script on a raspberry pi the baseURL will be
`http://xxx.xxx.xxx.xxx/vwid/`
where xxx.xxx.xxx.xxx is the IP of your raspberry pi.
If you've already made the ID DataLogger availible from the internet use `https://your-hostname.tld/vwid/` instead.

#### More settings

Further settings available are described in the following table:

| setting | explanation |
| ------- | ----------- |
| rangeInMiles      | set this to true if you want to have the range displayed in miles |
| showFinishTime    | set this to false if you want to hide the charge finish time and only display charge time remaining |
| forceImageRefresh | set this to true after you've changed the carPicture (data/carPic.png) to force the widget to update it |
| exampleData       | set this to true to force the widget to display example data and not to fetch from baseURL   |
| socThreshold      | not implemented  |