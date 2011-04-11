Purpose:
--------
1. These types of question enable to use same value of variables in some question of this type in quiz.
2. Also a lot of functions like sqrt and others can be used to calculate values of questions and answers or to show sign of root.

For example - if you define the variable X in question and set its range from 1 to 10, then inside the quiz the value of X is choosen randomly to be 5
 - it will be 5 in all parts of this question.


Installation Instructions:
---------------------
Put the folders phnumerical and phmultichoice in question->type , 
folder ph_finish_import in blocks.

This block is for the import process of these types of questions, it set whom every part belongs to.


Syntax:
-------
Every variable must be declare in the first question in this format ##{X}## ,in fields that are not HTML ,like answers in 'phnumerical' question,
the format is {X}.

In next level (after pressing save question) you define the values of each variables, it can be constant value or random between range
you specify. 

In order to create a new parts for a question and use these variables, you need to press on edit question (after saving) and press 'Sub Question(n)' for numerical 
part, and 'Sub Question(m)' for multichoice.

Notice:
------- 
only definition in first question are used in quiz, all defenition in other parts are only for preview in question list.

Examples:
----------
phnumerical question:
how long (in sec) take falling of object of mass ##{m}## kg from ##{h}## meters ,
when g is 10 m per sec, and its starting velocity is ##{v0}## meters per sec ?

answer:
0.1*(-{v0}+sqrt({v0}*{v0}+20*{h}))

phnumerical question:

g = 10 m sec-2
v0 = ##{n1}+10## m sec-1
a = ##{n2}/10+0.5## sec-1
calculate x after 5 sec

answer:

({n1}+10)*5+({n2}/10+0.5)*({n2}/10+0.5)*5

phmultichoice question :
what is the force (in newton) that particl in point (0, ##{n2}##, ##{n3}##) will fill in existant of following potential - 
U(x) = ##{n1}##sin(bx) + ##{n2}##x2 - ##{n3}##y2 + ##{n4}##z2 + ##{n5}##xy  + ##{n2}## [joule]
when b = 1 m^-1

choice1:
$${\bf F} = ##-{n1}-{n5}*{n2}##\hat{\bf x} ##withsign(2*{n3}*{n2})##\hat{\bf y} ##withsign(2*{n4}*{n3})##\hat{\bf y}$$ 

choice2:
$${\bf F} = ##{n1}-{n5}*{n2}##\hat{\bf x} ##withsign(-2*{n3}*{n2})##\hat{\bf y} ##withsign(-2*{n4}*{n3})##\hat{\bf y}$$ 

choice3:
$${\bf F} = ##+{n1}+{n5}*{n2}##\hat{\bf x} ##withsign(-2*{n3}*{n2})##\hat{\bf y} ##withsign(-2*{n4}*{n3})##\hat{\bf y}$$

choice4:
$${\bf F} = ##-{n1}-{n5}*{n1}##\hat{\bf x} ##withsign(2*{n3}*{n2})##\hat{\bf y} ##withsign(2*{n4}*{n2})##\hat{\bf y}$$ 

After declaring these variables (like {n1},{n2}) they will have the same value on all parts of this question.  


